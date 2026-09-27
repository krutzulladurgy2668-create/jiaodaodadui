<?php
/**
 * 飞书扫码登录 - 二维码生成与轮询（多重HTTP容错）
 */

define('FEISHU_APP_ID', 'cli_aabc65cf3138dbd4');
define('FEISHU_APP_SECRET', 'lXjCdCb5OjhdwUlnTSM7dbgKfckIIIu1');

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

session_start();
header('Content-Type: application/json; charset=utf-8');

// 飞书机器人通知辅助
require_once dirname(__FILE__) . '/feishu_bot.php';
header('Cache-Control: no-store, no-cache, must-revalidate');

$action = isset($_GET['action']) ? $_GET['action'] : '';

// ==================== 通用HTTP请求 ====================

function httpPost($url, $body = '', $headers = [], $timeout = 15) {
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
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response !== false && $code > 0) return ['code' => $code, 'body' => $response];
    }
    if (ini_get('allow_url_fopen')) {
        $hs = '';
        foreach ($headers as $h) $hs .= $h . "\r\n";
        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'header' => $hs, 'content' => $body,
            'timeout' => $timeout, 'ignore_errors' => true,
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]]);
        $r = @file_get_contents($url, false, $ctx);
        if ($r !== false) return ['code' => 200, 'body' => $r];
    }
    return null;
}

function httpGet($url, $headers = [], $timeout = 10) {
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
        $ctx = stream_context_create(['http' => [
            'method' => 'GET', 'header' => $hs, 'timeout' => $timeout,
            'ignore_errors' => true, 'ssl' => ['verify_peer' => false],
        ]]);
        $r = @file_get_contents($url, false, $ctx);
        if ($r !== false) return ['code' => 200, 'body' => $r];
    }
    return null;
}

function getTenantToken() {
    $url = "https://open.feishu.cn/open-apis/auth/v3/tenant_access_token/internal";
    $data = json_encode(['app_id' => FEISHU_APP_ID, 'app_secret' => FEISHU_APP_SECRET]);
    $result = httpPost($url, $data, ['Content-Type: application/json; charset=utf-8']);
    if ($result === null) return null;
    $json = json_decode($result['body'], true);
    return $json['tenant_access_token'] ?? null;
}

function fetchUserInfoByTicket($ticket, $token) {
    global $FEISHU_OPENID_NAME_MAP;
    if (empty($ticket)) return ['name' => '飞书用户', 'open_id' => '', 'avatar_url' => '', 'login_time' => date('Y-m-d H:i:s'), 'login_method' => 'feishu'];
    $url = "https://open.feishu.cn/open-apis/authen/v1/user_info_by_ticket?ticket=" . urlencode($ticket);
    $result = httpGet($url, ['Authorization: Bearer ' . $token]);
    if ($result === null || $result['code'] !== 200) return ['name' => '飞书用户', 'open_id' => '', 'avatar_url' => '', 'login_time' => date('Y-m-d H:i:s'), 'login_method' => 'feishu'];
    $json = json_decode($result['body'], true);
    $user = $json['data'] ?? [];
    $openId = $user['open_id'] ?? '';
    $name = $user['name'] ?? '飞书用户';
    if (!empty($openId) && isset($FEISHU_OPENID_NAME_MAP[$openId])) {
        $name = $FEISHU_OPENID_NAME_MAP[$openId];
    }
    return [
        'name' => $name,
        'open_id' => $openId,
        'avatar' => $user['avatar_url'] ?? '',
        'login_time' => date('Y-m-d H:i:s'),
        'login_method' => 'feishu'
    ];
}

// ==================== 创建二维码 ====================
if ($action === 'create') {
    $token = getTenantToken();
    if (!$token) {
        echo json_encode(['error' => '服务器无法连接飞书API，请检查网络设置']);
        exit;
    }

    $redirectUri = 'https://www.jiaodaodadui.com.cn/api/feishu_callback.php';
    $postData = json_encode([
        'app_id' => FEISHU_APP_ID,
        'redirect_uri' => $redirectUri,
        'scope' => ['contact:user.base:readonly']
    ]);

    $result = httpPost(
        "https://open.feishu.cn/open-apis/authen/v1/qr_code/start",
        $postData,
        ['Content-Type: application/json; charset=utf-8', 'Authorization: Bearer ' . $token]
    );

    if ($result === null) {
        echo json_encode(['error' => '获取二维码失败，请稍后重试']);
        exit;
    }

    $data = json_decode($result['body'], true);

    if (isset($data['code']) && $data['code'] != 0) {
        echo json_encode(['error' => ($data['msg'] ?? '获取二维码失败')]);
        exit;
    }

    $qrData = $data['data'] ?? [];
    $_SESSION['feishu_qr_key'] = $qrData['qrcode_key'] ?? '';

    echo json_encode([
        'qr_code' => $qrData['qrcode_url'] ?? '',
        'key'     => $qrData['qrcode_key'] ?? ''
    ]);
    exit;
}

// ==================== 轮询扫码状态 ====================
if ($action === 'poll') {
    $key = isset($_GET['key']) ? trim($_GET['key']) : '';
    if (empty($key)) { echo json_encode(['status' => 'expired']); exit; }

    $token = getTenantToken();
    if (!$token) { echo json_encode(['status' => 'pending']); exit; }

    $result = httpGet(
        "https://open.feishu.cn/open-apis/authen/v1/qr_code/scan?token=" . urlencode($key),
        ['Authorization: Bearer ' . $token]
    );

    if (!$result) { echo json_encode(['status' => 'pending']); exit; }

    $json = json_decode($result['body'], true);
    $status = $json['data']['scan_status'] ?? '';

    switch ($status) {
        case 'Scanned':
            echo json_encode(['status' => 'scanned']); break;
        case 'Confirmed':
            $userInfo = fetchUserInfoByTicket($json['data']['ticket'] ?? '', $token);

            // 记录会话并发送登录通知（失败不影响登录流程）
            try {
                $_SESSION['feishu_user'] = [
                    'open_id' => $userInfo['open_id'] ?? '',
                    'name' => $userInfo['name'] ?? '飞书用户',
                    'avatar' => $userInfo['avatar'] ?? '',
                    'login_time' => date('Y-m-d H:i:s'),
                    'login_method' => 'feishu'
                ];
                $_SESSION['is_logged_in'] = true;
                $_SESSION['login_method'] = 'feishu';
                notifyAdminLogin($userInfo['name'] ?? '', $userInfo['open_id'] ?? '', 'feishu');
            } catch (Throwable $e) {
                error_log('[飞书扫码登录通知] 发送失败: ' . $e->getMessage());
            }

            echo json_encode(['status' => 'confirmed', 'user_info' => $userInfo]); break;
        case 'Expired':
            echo json_encode(['status' => 'expired']); break;
        default:
            echo json_encode(['status' => 'pending']); break;
    }
    exit;
}

echo json_encode(['error' => '无效请求']);
?>
