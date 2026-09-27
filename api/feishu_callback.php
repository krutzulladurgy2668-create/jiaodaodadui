<?php
/**
 * 飞书 OAuth 登录回调（2026版API）
 * - 使用 v2 接口获取令牌
 * - 支持账号白名单（只有授权的飞书账号才能登录）
 */

// ==================== 配置区 ====================

define('FEISHU_APP_ID', 'cli_aabc65cf3138dbd4');
define('FEISHU_APP_SECRET', 'lXjCdCb5OjhdwUlnTSM7dbgKfckIIIu1');

// 飞书机器人通知辅助
require_once dirname(__FILE__) . '/feishu_bot.php';

// ★ 飞书登录白名单 ★
// 只有这里列出的 open_id 或 飞书姓名 才能登录管理后台
// 获取方式：先用任意飞书账号登录一次，查看宝塔PHP错误日志中的 [飞书登录] 记录，获取到 open_id 后填入下方
$FEISHU_WHITELIST = [
    // ★ 管理后台授权飞书账号（2026-06-18 配置）★
    'ou_2a30c407beb20348083f7efa5d6b7617', // 尹尚琦
    'ou_017c821add31817f157994de2c7d2e1a', // 何俊杰
    'ou_583121d3dffc91a651565e5755f7ec0f', // 张钰
    'ou_7dfffc71810d8fb90af6df4e92cc2dd6', // 廖敏婕
    'ou_3d06a6bc6dcd00f333fba715514f00e4', // 王雨婷
    'ou_34b784e9dbd643dc797a2fe34749cd8f', // 刘宇森
    'ou_bf15f6ca2da9eb484b1e9449d0021156', // 马小倩
    'ou_d445e2356e1afa63996c7a97f8ac34df', // 李苹
    'ou_8728403a376cd0bf3b0ec02ccdfb7abf', // 殷晓玲
    'ou_b2d817fcf16af695e26a04e2f8b919c7', // 郭子怡
    'ou_880ae7de967918f76580b9f1291c482a', // 刘千睿
    'ou_1ed1cfca80bb234c1745b2b8cf766444', // 王振宇
    'ou_aafbb8da36bdba123c0ea4d9b444027b', // 王梓全
    'ou_a894ce80541faed49d6cd2ec0216572b', // 蔡彬彬
    'ou_f8ae604e73a7fcbd2bb6eaa82df20834', // 陈怡璇
    'ou_9d45425fdba46f1f52e4d78ffb1da5c0', // 郑佳旺
    'ou_863b1e9c429012e7d2de58b937ef6b29', // 欧家
    'ou_26ed54574a0e9fb2afa07e368570c5ca', // 艾彦廷
    'ou_03054c909cb87d9e91bb861a1b7230b1', // 潘妍妍
    'ou_9192dc324effd62c8541c12af4623478', // 陈启蕊
];

// 是否开启白名单（设为 false 则所有飞书用户都能登录）
$ENABLE_WHITELIST = true;

// Open ID 对应真实姓名（确保已授权用户登录后显示正确姓名）
$FEISHU_OPENID_NAME_MAP = [
    'ou_2a30c407beb20348083f7efa5d6b7617' => '尹尚琦',
    'ou_017c821add31817f157994de2c7d2e1a' => '何俊杰',
    'ou_583121d3dffc91a651565e5755f7ec0f' => '张钰',
    'ou_7dfffc71810d8fb90af6df4e92cc2dd6' => '廖敏婕',
    'ou_3d06a6bc6dcd00f333fba715514f00e4' => '王雨婷',
    'ou_34b784e9dbd643dc797a2fe34749cd8f' => '刘宇森',
    'ou_bf15f6ca2da9eb484b1e9449d0021156' => '马小倩',
    'ou_d445e2356e1afa63996c7a97f8ac34df' => '李苹',
    'ou_8728403a376cd0bf3b0ec02ccdfb7abf' => '殷晓玲',
    'ou_b2d817fcf16af695e26a04e2f8b919c7' => '郭子怡',
    'ou_880ae7de967918f76580b9f1291c482a' => '刘千睿',
    'ou_1ed1cfca80bb234c1745b2b8cf766444' => '王振宇',
    'ou_aafbb8da36bdba123c0ea4d9b444027b' => '王梓全',
    'ou_a894ce80541faed49d6cd2ec0216572b' => '蔡彬彬',
    'ou_f8ae604e73a7fcbd2bb6eaa82df20834' => '陈怡璇',
    'ou_9d45425fdba46f1f52e4d78ffb1da5c0' => '郑佳旺',
    'ou_863b1e9c429012e7d2de58b937ef6b29' => '欧家',
    'ou_26ed54574a0e9fb2afa07e368570c5ca' => '艾彦廷',
    'ou_03054c909cb87d9e91bb861a1b7230b1' => '潘妍妍',
    'ou_9192dc324effd62c8541c12af4623478' => '陈启蕊',
];

// ==================== 初始化 ====================

error_reporting(E_ALL);
ini_set('display_errors', 0); // 不显示PHP错误，用自定义提示
session_start();

// 辅助函数：安全输出HTML页面
function outputPage($title, $body) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . htmlspecialchars($title) . '</title>';
    echo '<style>body{font-family:-apple-system,"Segoe UI",sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f5f5f5;margin:0}.box{text-align:center;max-width:420px;padding:32px;background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.08)}.box h2{color:#333;margin:0 0 12px;font-size:20px}.box p{color:#666;margin:0 0 20px;line-height:1.6;font-size:14px}.btn{display:inline-block;padding:10px 28px;border-radius:6px;text-decoration:none;font-size:14px;font-weight:500;transition:.2s}.btn-primary{background:#3370FF;color:#fff}.btn-primary:hover{background:#2860e1}.btn-danger{background:#fee;color:#e74c3c;border:1px solid #fadbd8}.btn-danger:hover{background:#fef5f5}</style>';
    echo '</head><body><div class="box">' . $body . '</div></body></html>';
    exit;
}

function httpPost($url, $body = '', $headers = [], $timeout = 30) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response !== false && $httpCode > 0) return ['code' => $httpCode, 'body' => $response];
    }
    if (ini_get('allow_url_fopen')) {
        $headerStr = '';
        foreach ($headers as $h) { $headerStr .= $h . "\r\n"; }
        $opts = ['http' => ['method' => 'POST', 'header' => $headerStr, 'content' => $body, 'timeout' => $timeout, 'ignore_errors' => true, 'ssl' => ['verify_peer' => false]]];
        $r = @file_get_contents($url, false, stream_context_create($opts));
        if ($r !== false) return ['code' => 200, 'body' => $r];
    }
    return null;
}

function httpGet($url, $headers = [], $timeout = 30) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $r = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($r !== false) return ['code' => $c, 'body' => $r];
    }
    if (ini_get('allow_url_fopen')) {
        $hs = ''; foreach ($headers as $h) $hs .= $h . "\r\n";
        $opts = ['http' => ['method' => 'GET', 'header' => $hs, 'timeout' => $timeout, 'ignore_errors' => true, 'ssl' => ['verify_peer' => false]]];
        $r = @file_get_contents($url, false, stream_context_create($opts));
        if ($r !== false) return ['code' => 200, 'body' => $r];
    }
    return null;
}

// ==================== 第一步：获取授权码 ====================

$code = isset($_GET['code']) ? $_GET['code'] : '';
if (empty($code)) {
    $redirect_uri = urlencode('https://www.jiaodao.fun/api/feishu_callback.php');
    $auth_url = "https://accounts.feishu.cn/open-apis/authen/v1/authorize?app_id=" . FEISHU_APP_ID . "&redirect_uri=" . $redirect_uri . "&scope=contact:user.base:readonly";
    header('Location: ' . $auth_url);
    exit;
}

// ==================== 第二步：换取 access_token ====================

$token_url = "https://open.feishu.cn/open-apis/authen/v2/oauth/token";
$token_data = json_encode([
    'grant_type' => 'authorization_code',
    'client_id' => FEISHU_APP_ID,
    'client_secret' => FEISHU_APP_SECRET,
    'code' => $code,
    'redirect_uri' => 'https://www.jiaodao.fun/api/feishu_callback.php'
]);

$result = httpPost($token_url, $token_data, [
    'Content-Type: application/json; charset=utf-8',
    'Content-Length: ' . strlen($token_data)
]);

if (!$result) {
    outputPage('连接失败', '<h2>连接失败</h2><p>服务器无法连接到飞书API</p><a href="../admin.html" class="btn btn-primary">返回登录页</a>');
}

$response = $result['body'];
$http_code = $result['code'];

if ($http_code !== 200) {
    error_log("[飞书登录] Token接口 HTTP:$http_code 响应:$response");
    outputPage('登录失败', '<h2>登录失败</h2><p>令牌接口异常 (HTTP ' . $http_code . ')</p><a href="../admin.html" class="btn btn-primary">返回登录页</a>');
}

$token_result = json_decode($response, true);

if (!$token_result) {
    error_log("[飞书登录] JSON解析失败: $response");
    outputPage('登录失败', '<h2>登录失败</h2><p>响应数据解析失败</p><p style="font-size:12px;color:#999;margin-top:12px">调试信息: ' . htmlspecialchars(substr($response, 0, 300)) . '</p><a href="../admin.html" class="btn btn-primary">返回登录页</a>');
}

if (!empty($token_result['code']) && $token_result['code'] != 0) {
    $msg = $token_result['msg'] ?? '未知错误';
    error_log("[飞书登录] API错误 code={$token_result['code']} msg=$msg");
    outputPage('登录失败', '<h2>登录失败</h2><p>' . htmlspecialchars($msg) . ' (错误码: ' . $token_result['code'] . ')</p><a href="../admin.html" class="btn btn-primary">返回登录页</a>');
}

// 兼容多种响应格式
$accessToken = $token_result['data']['access_token']
            ?? $token_result['access_token']
            ?? null;

if (!$accessToken) {
    error_log("[飞书登录] 无法提取access_token, 完整响应: $response");
    outputPage('登录失败', '<h2>登录失败</h2><p>无法获取访问令牌</p><p style="font-size:12px;color:#999;margin-top:12px">调试信息: ' . htmlspecialchars(substr(json_encode($token_result, JSON_UNESCAPED_UNICODE), 0, 400)) . '</p><a href="../admin.html" class="btn btn-primary">返回登录页</a>');
}

// ==================== 第三步：获取用户信息 ====================

$user_info_url = "https://open.feishu.cn/open-apis/authen/v1/user_info";
$userResult = httpGet($user_info_url, [
    'Authorization: Bearer ' . $accessToken,
    'Content-Type: application/json; charset=utf-8'
]);

$user = ['name' => '飞书用户', 'open_id' => '', 'avatar_url' => '', 'email' => '', 'mobile' => ''];

if ($userResult && $userResult['code'] === 200) {
    $ui = json_decode($userResult['body'], true);
    if (isset($ui['data'])) {
        $user = array_merge($user, $ui['data']);
    }
}

// 如果本地映射中有该 Open ID，使用映射的真实姓名（避免飞书接口未返回姓名时显示"飞书用户"）
if (!empty($user['open_id']) && isset($FEISHU_OPENID_NAME_MAP[$user['open_id']])) {
    $user['name'] = $FEISHU_OPENID_NAME_MAP[$user['open_id']];
}

// 记录登录信息（用于获取白名单ID）
error_log("[飞书登录] 用户信息: name={$user['name']}, open_id={$user['open_id']}, email=" . ($user['email'] ?? ''));

// ==================== 第四步：白名单校验 ====================

$isAuthorized = true;
if ($ENABLE_WHITELIST && !empty($FEISHU_WHITELIST)) {
    $isAuthorized = false;
    foreach ($FEISHU_WHITELIST as $allowed) {
        if ($allowed === $user['open_id'] || $allowed === $user['name']) {
            $isAuthorized = true;
            break;
        }
    }
}

if (!$isAuthorized) {
    error_log("[飞书登录] 未授权用户被拒绝: name={$user['name']}, open_id={$user['open_id']}");
    outputPage('无权限',
        '<h2>无权限访问</h2>'
        . '<p>您的飞书账号 <strong>' . htmlspecialchars($user['name']) . '</strong> 不在管理后台授权名单中。</p>'
        . '<p style="font-size:13px;color:#888;">如需开通权限，请联系网站管理员将您的账号加入白名单。</p>'
        . '<a href="../admin.html" class="btn btn-primary" style="margin-top:16px">返回登录页</a>'
    );
}

// ==================== 第五步：登录成功 ====================

$_SESSION['feishu_user'] = [
    'open_id' => $user['open_id'],
    'name' => $user['name'],
    'avatar' => $user['avatar_url'] ?? $user['avatar_thumb'] ?? '',
    'email' => $user['email'] ?? '',
    'mobile' => $user['mobile'] ?? '',
    'login_time' => date('Y-m-d H:i:s'),
    'login_method' => 'feishu'
];
$_SESSION['is_logged_in'] = true;
$_SESSION['login_method'] = 'feishu';

// 发送飞书群机器人登录通知（失败不影响登录流程）
try {
    notifyAdminLogin($user['name'] ?? '', $user['open_id'] ?? '', 'feishu');
} catch (Throwable $e) {
    error_log('[飞书登录通知] 发送失败: ' . $e->getMessage());
}

// 写入登录日志到服务器（失败不影响登录流程）
try {
    $logData = json_encode(['action' => 'add', 'data' => [
        'name' => $user['name'] ?? '',
        'openId' => $user['open_id'] ?? '',
        'method' => 'feishu'
    ]]);
    $ch = curl_init(dirname(__FILE__) . '/login_logs.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $logData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
} catch (Throwable $e) {
    error_log('[登录日志] 写入失败: ' . $e->getMessage());
}

// 输出跳转页面（双重跳转：JS + meta refresh）
$targetUrl = "../admin.html?login=feishu&success=1&name=" . urlencode($user['name']);
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
echo '<meta http-equiv="refresh" content="1;url=' . htmlspecialchars($targetUrl) . '">';
echo '<title>登录成功 - 正在跳转</title>';
echo '<style>body{font-family:-apple-system,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:linear-gradient(135deg,#667eea,#764ba2);margin:0}.box{text-align:center;color:#fff}.spinner{width:40px;height:40px;border:3px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 20px}@keyframes spin{to{transform:rotate(360deg)}}h2{margin:0 0 8px;font-size:22px}p{opacity:.8;margin:0;font-size:14px}.manual-link{display:inline-block;margin-top:20px;padding:10px 24px;background:rgba(255,255,255,.15);color:#fff;border-radius:6px;text-decoration:none;font-size:13px}.manual-link:hover{background:rgba(255,255,255,.25)}</style>';
echo '</head><body><div class="box">';
echo '<div class="spinner"></div>';
echo '<h2>登录成功</h2>';
echo '<p>' . htmlspecialchars($user['name']) . '，欢迎回来！正在跳转到管理后台...</p>';
echo '<a href="' . htmlspecialchars($targetUrl) . '" class="manual-link">若未自动跳转，请点击这里</a>';
echo '<script>
localStorage.setItem("feishu_login", JSON.stringify(' . json_encode($_SESSION['feishu_user'], JSON_UNESCAPED_UNICODE) . '));
setTimeout(function(){ window.location.href = "' . $targetUrl . '"; }, 800);
</script>';
echo '</div></body></html>';
?>
