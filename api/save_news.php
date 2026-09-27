<?php
/**
 * 新闻数据同步接口（高可靠性版本）
 * - 写入前强制备份
 * - 空数据保护（不会误清空已有新闻）
 * - 自动从备份恢复（主文件为空/损坏时）
 * - 支持 merge 模式合并数据
 * - 原子写入（LOCK_EX）
 *
 * 使用方式: POST /api/save_news.php
 * Body: { mode: 'save'|'merge'|'restore'|'read', data: [...] }
 */

// 飞书机器人通知辅助
require_once dirname(__FILE__) . '/feishu_bot.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Operator-Name, X-Operator-OpenId, X-Operator-Method');

// 放宽 PHP 限制：新闻数据可能包含 base64 图片，避免大请求被截断
@ini_set('memory_limit', '256M');
@ini_set('post_max_size', '50M');
@ini_set('upload_max_filesize', '50M');
@ini_set('max_input_vars', '10000');
@ini_set('max_execution_time', '60');

// 从请求体中读取操作人信息（避免中文请求头问题）
function getOperatorFromRequest($payload = null) {
    $name = '未知';
    $openId = '';
    
    if ($payload && isset($payload['operator']) && is_array($payload['operator'])) {
        $name = isset($payload['operator']['name']) ? trim($payload['operator']['name']) : '未知';
        $openId = isset($payload['operator']['openId']) ? trim($payload['operator']['openId']) : '';
    }
    
    if (empty($name) && session_status() === PHP_SESSION_NONE) @session_start();
    if (empty($name) && !empty($_SESSION['feishu_user']['name'])) {
        $name = $_SESSION['feishu_user']['name'];
        $openId = $_SESSION['feishu_user']['open_id'] ?? '';
    }

    return [empty($name) ? '未知' : $name, $openId];
}

// 调试日志：记录每次请求的关键信息，便于排查同步失败
function saveNewsDebugLog($msg, $data = null) {
    $logFile = dirname(__FILE__) . '/../logs/save_news_debug.log';
    $dir = dirname($logFile);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $line = date('Y-m-d H:i:s') . ' ' . $msg;
    if ($data !== null) $line .= ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    $line .= PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

saveNewsDebugLog('请求开始', ['method' => $_SERVER['REQUEST_METHOD'], 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']);

// 错误捕获：把所有错误/异常/fatal error 转成 JSON 返回，避免前端收到 HTML 错误页面
function saveNewsJsonError($msg, $detail = '') {
    saveNewsDebugLog('错误返回', ['message' => $msg, 'detail' => $detail]);
    @http_response_code(500);
    echo json_encode(['success' => false, 'message' => $msg, 'detail' => $detail]);
    exit;
}

set_error_handler(function ($severity, $message, $file, $line) {
    saveNewsDebugLog('PHP错误', ['severity' => $severity, 'message' => $message, 'file' => $file, 'line' => $line]);
    if ($severity === E_ERROR || $severity === E_PARSE || $severity === E_CORE_ERROR || $severity === E_COMPILE_ERROR) {
        saveNewsJsonError('服务器内部错误', $message . ' in ' . basename($file) . ':' . $line);
    }
    return true;
});

set_exception_handler(function ($e) {
    saveNewsDebugLog('未捕获异常', ['message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    saveNewsJsonError('服务器异常', $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine());
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        saveNewsDebugLog('Fatal错误', $error);
        saveNewsJsonError('服务器致命错误', $error['message'] . ' in ' . basename($error['file']) . ':' . $error['line']);
    }
});

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '只支持 POST 请求']);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = [];
if (!empty($rawInput)) {
    $payload = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'JSON 解析失败: ' . json_last_error_msg()]);
        exit;
    }
}

$mode = isset($payload['mode']) ? $payload['mode'] : 'save';
$targetFile = dirname(__FILE__) . '/../news_data.json';
$backupDir = dirname(__FILE__) . '/../backups';

saveNewsDebugLog('解析请求体', [
    'mode' => $mode,
    'payload_data_count' => isset($payload['data']) && is_array($payload['data']) ? count($payload['data']) : 'missing',
    'raw_input_length' => strlen($rawInput),
    'operator_name' => isset($payload['operator']['name']) ? $payload['operator']['name'] : '未提供',
    'operator_openid' => isset($payload['operator']['openId']) ? $payload['operator']['openId'] : '未提供'
]);

// 确保备份目录存在
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0755, true);
}

// 获取最新备份内容
function getLatestBackup($backupDir) {
    $files = glob($backupDir . '/news_data.*.json');
    if (!$files || count($files) === 0) return null;
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    foreach ($files as $file) {
        $raw = @file_get_contents($file);
        $data = json_decode($raw, true);
        if ($data && is_array($data) && count($data) > 0) {
            return $data;
        }
    }
    return null;
}

// 读取主文件，如果为空/损坏则自动从备份恢复
function readMainData($targetFile, $backupDir, &$recoveredFromBackup) {
    $recoveredFromBackup = false;
    $raw = @file_get_contents($targetFile);
    if ($raw !== false) {
        $data = json_decode($raw, true);
        if ($data && is_array($data) && count($data) > 0) {
            return $data;
        }
    }
    $backup = getLatestBackup($backupDir);
    if ($backup && count($backup) > 0) {
        $recoveredFromBackup = true;
        @file_put_contents($targetFile, json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        return $backup;
    }
    return [];
}

// 清理旧备份，保留最近20个
function cleanupBackups($backupDir) {
    $files = glob($backupDir . '/news_data.*.json');
    if (!$files || count($files) <= 20) return;
    usort($files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    for ($i = 0; $i < count($files) - 20; $i++) {
        @unlink($files[$i]);
    }
}

// 标准化单条新闻
function normalizeNewsItem($item) {
    if (!is_array($item)) return null;
    $id = isset($item['id']) ? intval($item['id']) : (time() * 1000 + rand(0, 999));
    $category = isset($item['category']) ? trim($item['category']) : '新闻动态';
    if (!in_array($category, ['新闻动态', '专题专栏', '资料下载'], true)) {
        $category = !empty($item['downloadUrl']) ? '资料下载' : '新闻动态';
    }
    $topic = isset($item['topic']) ? trim($item['topic']) : '';
    if ($category !== '专题专栏' || !in_array($topic, ['军训专栏', '换届专题专栏'], true)) {
        $topic = '';
    }
    $downloadUrl = isset($item['downloadUrl']) ? trim($item['downloadUrl']) : '';
    $downloadName = isset($item['downloadName']) ? trim($item['downloadName']) : '';
    if ($category !== '资料下载') {
        $downloadUrl = '';
        $downloadName = '';
    }
    return [
        'id'        => $id,
        'title'     => isset($item['title']) ? htmlspecialchars(trim($item['title']), ENT_QUOTES, 'UTF-8') : '(无标题)',
        'author'    => isset($item['author']) ? htmlspecialchars(trim($item['author']), ENT_QUOTES, 'UTF-8') : '宣传部',
        'date'      => isset($item['date']) ? trim($item['date']) : date('Y-m-d'),
        'desc'      => isset($item['desc']) ? htmlspecialchars(trim($item['desc']), ENT_QUOTES, 'UTF-8') : '',
        'content'   => isset($item['content']) ? $item['content'] : '',
        'status'    => isset($item['status']) && $item['status'] === 'draft' ? 'draft' : 'published',
        'views'     => isset($item['views']) ? intval($item['views']) : 0,
        'images'    => isset($item['images']) && is_array($item['images']) ? $item['images'] : [],
        'category'  => $category,
        'topic'     => $topic,
        'downloadUrl'  => $downloadUrl,
        'downloadName' => htmlspecialchars($downloadName, ENT_QUOTES, 'UTF-8'),
        'updatedAt' => isset($item['updatedAt']) ? intval($item['updatedAt']) : (time() * 1000)
    ];
}

// 过滤并标准化数组（save 模式用：过滤掉草稿和空内容）
function normalizeArray($data) {
    $result = [];
    if (!is_array($data)) return $result;
    foreach ($data as $item) {
        $normalized = normalizeNewsItem($item);
        if ($normalized && $normalized['status'] !== 'draft' && !empty($normalized['title']) && !empty($normalized['content'])) {
            $result[] = $normalized;
        }
    }
    return $result;
}

// 标准化数组（merge/delete 模式用：保留草稿和状态变更）
function normalizeMergeData($data) {
    $result = [];
    if (!is_array($data)) return $result;
    foreach ($data as $item) {
        $normalized = normalizeNewsItem($item);
        if ($normalized && !empty($normalized['title'])) {
            $result[] = $normalized;
        }
    }
    return $result;
}

// 合并两组新闻：以 id 为键，保留 updatedAt 更大的
function mergeNews($local, $remote) {
    $map = [];
    foreach ($local as $item) {
        if ($item && isset($item['id'])) $map[$item['id']] = $item;
    }
    foreach ($remote as $item) {
        if (!$item || !isset($item['id'])) continue;
        $id = $item['id'];
        if (!isset($map[$id])) {
            $map[$id] = $item;
        } else {
            $localTs = isset($map[$id]['updatedAt']) ? intval($map[$id]['updatedAt']) : 0;
            $remoteTs = isset($item['updatedAt']) ? intval($item['updatedAt']) : 0;
            if ($remoteTs > $localTs) {
                $map[$id] = $item;
            }
        }
    }
    $merged = array_values($map);
    usort($merged, function($a, $b) {
        return strcmp($b['date'], $a['date']);
    });
    return $merged;
}

// 执行备份
function backupFile($targetFile, $backupDir) {
    if (!file_exists($targetFile)) return;
    $backupFile = $backupDir . '/news_data.' . date('YmdHis') . '.' . sprintf('%03d', rand(0, 999)) . '.json';
    @copy($targetFile, $backupFile);
    cleanupBackups($backupDir);
}

// 写入主文件
function writeMainData($targetFile, $data) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) return false;
    $tmpFile = $targetFile . '.tmp';
    if (@file_put_contents($tmpFile, $json, LOCK_EX) === false) return false;
    return @rename($tmpFile, $targetFile);
}

// 处理 read 模式
if ($mode === 'read') {
    $recovered = false;
    $data = readMainData($targetFile, $backupDir, $recovered);
    echo json_encode([
        'success' => true,
        'count' => count($data),
        'recoveredFromBackup' => $recovered,
        'data' => $data,
        'time' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// 处理 restore 模式
if ($mode === 'restore') {
    $backup = getLatestBackup($backupDir);
    if (!$backup || count($backup) === 0) {
        echo json_encode(['success' => false, 'message' => '没有可用的备份文件']);
        exit;
    }
    if (file_exists($targetFile)) backupFile($targetFile, $backupDir);
    if (writeMainData($targetFile, $backup)) {
        echo json_encode([
            'success' => true,
            'message' => '已从备份恢复 ' . count($backup) . ' 条新闻',
            'count' => count($backup),
            'time' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '恢复失败，请检查文件权限']);
    }
    exit;
}

// 读取当前服务器数据
$recovered = false;
$currentData = readMainData($targetFile, $backupDir, $recovered);
saveNewsDebugLog('读取当前数据', ['mode' => $mode, 'current_count' => count($currentData), 'recovered' => $recovered]);

// 处理 save 模式（提交完整数组）
if ($mode === 'save') {
    $inputData = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;
    $newData = normalizeArray($inputData);

    // 空数据保护：提交为空但服务器有数据时，拒绝覆盖
    if (count($newData) === 0 && count($currentData) > 0) {
        echo json_encode([
            'success' => false,
            'message' => '同步被拒绝：提交的数据为空或均为草稿，但服务器上已有 ' . count($currentData) . ' 条新闻，未覆盖',
            'count' => count($currentData),
            'serverDataSample' => array_slice($currentData, 0, 3)
        ]);
        saveNewsDebugLog('空数据保护拒绝', ['current_count' => count($currentData)]);
        exit;
    }

    // 合并而不是简单覆盖，确保不丢失服务器上的数据
    $finalData = mergeNews($currentData, $newData);
    saveNewsDebugLog('save模式合并完成', ['input_count' => count($inputData), 'new_count' => count($newData), 'final_count' => count($finalData)]);
}
// 处理 merge 模式（合并单条/部分数据，用于增量同步）
elseif ($mode === 'merge') {
    $inputData = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
    $newData = normalizeMergeData($inputData);
    $finalData = mergeNews($currentData, $newData);
    saveNewsDebugLog('merge模式合并完成', ['input_count' => count($inputData), 'new_count' => count($newData), 'final_count' => count($finalData)]);
}
// 处理 delete 模式（按 ID 删除）
elseif ($mode === 'delete') {
    $idsToDelete = isset($payload['ids']) && is_array($payload['ids']) ? $payload['ids'] : [];
    if (count($idsToDelete) === 0) {
        echo json_encode(['success' => false, 'message' => '请提供要删除的新闻 ID']);
        exit;
    }
    $finalData = array_values(array_filter($currentData, function($item) use ($idsToDelete) {
        return !in_array($item['id'] ?? null, $idsToDelete, true);
    }));
    saveNewsDebugLog('delete模式完成', ['ids' => $idsToDelete, 'before' => count($currentData), 'after' => count($finalData)]);
} else {
    saveNewsDebugLog('未知模式', ['mode' => $mode]);
    echo json_encode(['success' => false, 'message' => '未知模式: ' . $mode]);
    exit;
}

// 写入前备份
backupFile($targetFile, $backupDir);
saveNewsDebugLog('备份完成，准备写入');

if (writeMainData($targetFile, $finalData)) {
    // 发送飞书群机器人新闻变更通知（失败不影响保存结果）
    try {
        list($operatorName, $operatorOpenId) = getOperatorFromRequest($payload);
        saveNewsDebugLog('准备发送新闻变更通知', ['operator' => $operatorName, 'open_id' => $operatorOpenId, 'old_count' => count($currentData), 'new_count' => count($finalData)]);
        $notifyResult = notifyNewsChanged($operatorName, $operatorOpenId, $currentData, $finalData);
        saveNewsDebugLog('新闻变更通知结果', [
            'sent' => ($notifyResult !== false),
            'result' => is_array($notifyResult) ? $notifyResult : ($notifyResult === false ? 'false (无变更或发送失败)' : 'true')
        ]);
        if ($notifyResult === false) {
            error_log('[飞书通知] 新闻变更通知未发送，可能原因：无实际变更 / curl失败 / 网络问题。operator=' . $operatorName . ', openId=' . $operatorOpenId);
        } elseif (is_array($notifyResult)) {
            $httpCode = $notifyResult['code'] ?? 0;
            $body = $notifyResult['body'] ?? '';
            error_log('[飞书通知] 新闻变更通知已发送，HTTP=' . $httpCode . ', response=' . substr($body, 0, 200));
        }
    } catch (Throwable $e) {
        saveNewsDebugLog('新闻变更通知异常', ['error' => $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()]);
        error_log('[新闻变更通知] 发送失败: ' . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => '同步成功！共 ' . count($finalData) . ' 条新闻已更新到官网',
        'count' => count($finalData),
        'recoveredFromBackup' => $recovered,
        'time' => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode(['success' => false, 'message' => '写入文件失败，请检查目录权限']);
}
