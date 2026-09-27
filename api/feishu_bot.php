<?php
/**
 * 飞书机器人消息推送辅助类
 * - 支持文本、富文本、卡片消息
 * - 失败时记录到 PHP 错误日志，不影响主流程
 */

// 飞书群机器人 Webhook（自定义机器人）
if (!defined('FEISHU_BOT_WEBHOOK')) {
    define('FEISHU_BOT_WEBHOOK', 'https://open.feishu.cn/open-apis/bot/v2/hook/bdebb5c8-75a8-4130-8d7d-0a47f1c5d3d6');
}

/**
 * 发送飞书机器人消息（通用 POST 请求）
 */
function feishuBotHttpPost($url, $payload, $timeout = 8) {
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response !== false && $httpCode > 0) {
            return ['code' => $httpCode, 'body' => $response];
        }
    }

    if (ini_get('allow_url_fopen')) {
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json; charset=utf-8\r\n",
                'content' => $json,
                'timeout' => $timeout,
                'ignore_errors' => true
            ],
            'ssl' => ['verify_peer' => false]
        ];
        $r = @file_get_contents($url, false, stream_context_create($opts));
        if ($r !== false) return ['code' => 200, 'body' => $r];
    }

    return false;
}

/**
 * 获取客户端 IP
 */
function feishuBotClientIp() {
    $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '未知';
}

/**
 * 发送文本消息
 */
function sendFeishuText($text) {
    return feishuBotHttpPost(FEISHU_BOT_WEBHOOK, ['msg_type' => 'text', 'content' => ['text' => $text]]);
}

/**
 * 发送卡片消息（更美观，支持标题、颜色、分隔线）
 */
function sendFeishuCard($config) {
    $card = [
        'config' => ['wide_screen_mode' => true, 'enable_forward' => true],
        'header' => [
            'title' => ['tag' => 'plain_text', 'content' => $config['title']],
            'template' => $config['color'] ?? 'blue'
        ],
        'elements' => []
    ];

    foreach ($config['sections'] as $section) {
        if ($section['type'] === 'divider') {
            $card['elements'][] = ['tag' => 'hr'];
        } elseif ($section['type'] === 'text') {
            $card['elements'][] = [
                'tag' => 'div',
                'text' => ['tag' => 'plain_text', 'content' => $section['content']]
            ];
        } elseif ($section['type'] === 'markdown') {
            $card['elements'][] = [
                'tag' => 'div',
                'text' => ['tag' => 'lark_md', 'content' => $section['content']]
            ];
        } elseif ($section['type'] === 'fields') {
            $fields = [];
            foreach ($section['items'] as $item) {
                $fields[] = ['is_short' => true, 'text' => ['tag' => 'lark_md', 'content' => $item]];
            }
            $card['elements'][] = ['tag' => 'div', 'fields' => $fields];
        }
    }

    return feishuBotHttpPost(FEISHU_BOT_WEBHOOK, [
        'msg_type' => 'interactive',
        'card' => $card
    ]);
}

/**
 * 发送富文本消息
 */
function sendFeishuPost($title, $lines) {
    $content = [];
    foreach ($lines as $line) {
        $content[] = array_map(function ($part) {
            if (is_string($part)) return ['tag' => 'text', 'text' => $part];
            if (isset($part['tag'])) return $part;
            return ['tag' => 'text', 'text' => ''];
        }, $line);
    }
    return feishuBotHttpPost(FEISHU_BOT_WEBHOOK, [
        'msg_type' => 'post',
        'content' => ['post' => ['zh_cn' => ['title' => $title, 'content' => $content]]]
    ]);
}

/**
 * 发送管理后台登录通知（卡片消息格式）
 */
function notifyAdminLogin($name, $openId, $method, $extra = []) {
    $time = date('Y-m-d H:i:s');
    $methodText = $method === 'feishu' ? '飞书登录' : '账号密码登录';
    $ip = $extra['ip'] ?? feishuBotClientIp();
    $ua = $extra['ua'] ?? (isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 120) : '未知');

    return sendFeishuCard([
        'title' => '🛡️ 管理后台登录提醒',
        'color' => 'blue',
        'sections' => [
            ['type' => 'markdown', 'content' => '**登录人员**：' . ($name ?: '未知')],
            ['type' => 'markdown', 'content' => '**Open ID**：' . ($openId ?: '未获取')],
            ['type' => 'markdown', 'content' => '**登录方式**：' . $methodText],
            ['type' => 'markdown', 'content' => '**登录时间**：' . $time],
            ['type' => 'hr'],
            ['type' => 'markdown', 'content' => '**IP 地址**：' . $ip],
            ['type' => 'markdown', 'content' => '**设备信息**：' . $ua]
        ]
    ]);
}

/**
 * 发送新闻数据变更通知（卡片消息格式）
 */
function notifyNewsChanged($operatorName, $operatorOpenId, $oldData, $newData, $extra = []) {
    $time = date('Y-m-d H:i:s');
    $oldMap = [];
    $newMap = [];
    foreach ($oldData as $item) if (isset($item['id'])) $oldMap[$item['id']] = $item;
    foreach ($newData as $item) if (isset($item['id'])) $newMap[$item['id']] = $item;

    $added = [];
    $deleted = [];
    $modified = [];

    foreach ($newMap as $id => $item) {
        if (!isset($oldMap[$id])) {
            $added[] = $item;
        } else {
            $oldItem = $oldMap[$id];
            $oldHash = md5(json_encode([
                $oldItem['title'] ?? '',
                $oldItem['content'] ?? '',
                $oldItem['desc'] ?? '',
                $oldItem['author'] ?? '',
                $oldItem['date'] ?? '',
                $oldItem['status'] ?? 'published',
                $oldItem['updatedAt'] ?? 0
            ]));
            $newHash = md5(json_encode([
                $item['title'] ?? '',
                $item['content'] ?? '',
                $item['desc'] ?? '',
                $item['author'] ?? '',
                $item['date'] ?? '',
                $item['status'] ?? 'published',
                $item['updatedAt'] ?? 0
            ]));
            if ($oldHash !== $newHash) {
                $modified[] = [
                    'title' => $item['title'] ?? '(无标题)',
                    'desc' => $item['desc'] ?? '',
                    'old_status' => $oldItem['status'] ?? 'published',
                    'new_status' => $item['status'] ?? 'published',
                    'old_title' => $oldItem['title'] ?? '',
                    'old_desc' => $oldItem['desc'] ?? ''
                ];
            }
        }
    }
    foreach ($oldMap as $id => $item) {
        if (!isset($newMap[$id])) $deleted[] = $item;
    }

    $sections = [
        ['type' => 'markdown', 'content' => '**操作人员**：' . ($operatorName ?: '未知')],
        ['type' => 'markdown', 'content' => '**Open ID**：' . ($operatorOpenId ?: '未获取')],
        ['type' => 'markdown', 'content' => '**操作时间**：' . $time],
        ['type' => 'hr'],
        ['type' => 'markdown', 'content' => '**变更统计**：新增 ' . count($added) . ' 条 / 删除 ' . count($deleted) . ' 条 / 修改 ' . count($modified) . ' 条']
    ];

    $maxShow = 5;
    if (count($modified) > 0) {
        $sections[] = ['type' => 'hr'];
        $sections[] = ['type' => 'markdown', 'content' => '**✏️ 修改内容**：'];
        foreach (array_slice($modified, 0, $maxShow) as $item) {
            $changes = [];
            if ($item['old_title'] !== $item['title']) {
                $changes[] = '标题：' . (mb_strlen($item['old_title']) > 20 ? mb_substr($item['old_title'], 0, 20) . '...' : $item['old_title']) . ' → ' . (mb_strlen($item['title']) > 20 ? mb_substr($item['title'], 0, 20) . '...' : $item['title']);
            }
            if ($item['old_desc'] !== $item['desc']) {
                $changes[] = '简介：' . (mb_strlen($item['old_desc']) > 30 ? mb_substr($item['old_desc'], 0, 30) . '...' : $item['old_desc']) . ' → ' . (mb_strlen($item['desc']) > 30 ? mb_substr($item['desc'], 0, 30) . '...' : $item['desc']);
            }
            if ($item['old_status'] !== $item['new_status']) {
                $changes[] = '状态：' . $item['old_status'] . ' → ' . $item['new_status'];
            }
            if (empty($changes)) {
                $changes[] = '内容已修改';
            }
            $sections[] = ['type' => 'markdown', 'content' => '• ' . implode('；', $changes)];
        }
        if (count($modified) > $maxShow) $sections[] = ['type' => 'markdown', 'content' => '• ... 等 ' . count($modified) . ' 条'];
    }
    if (count($added) > 0) {
        $sections[] = ['type' => 'hr'];
        $sections[] = ['type' => 'markdown', 'content' => '**🆕 新增内容**：'];
        foreach (array_slice($added, 0, $maxShow) as $item) {
            $sections[] = ['type' => 'markdown', 'content' => '• 标题：' . ($item['title'] ?? '(无标题)')];
        }
        if (count($added) > $maxShow) $sections[] = ['type' => 'markdown', 'content' => '• ... 等 ' . count($added) . ' 条'];
    }
    if (count($deleted) > 0) {
        $sections[] = ['type' => 'hr'];
        $sections[] = ['type' => 'markdown', 'content' => '**🗑️ 删除内容**：'];
        foreach (array_slice($deleted, 0, $maxShow) as $item) {
            $sections[] = ['type' => 'markdown', 'content' => '• 标题：' . ($item['title'] ?? '(无标题)')];
        }
        if (count($deleted) > $maxShow) $sections[] = ['type' => 'markdown', 'content' => '• ... 等 ' . count($deleted) . ' 条'];
    }

    $ip = $extra['ip'] ?? feishuBotClientIp();
    $sections[] = ['type' => 'hr'];
    $sections[] = ['type' => 'markdown', 'content' => '**IP 地址**：' . $ip];

    return sendFeishuCard([
        'title' => '📰 大队官网新闻数据变更通知',
        'color' => 'green',
        'sections' => $sections
    ]);
}
