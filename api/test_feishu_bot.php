<?php
/**
 * 飞书机器人测试脚本
 * 访问方式：浏览器打开 /api/test_feishu_bot.php
 * 作用：验证 Webhook 是否能正常推送、服务器是否能访问 open.feishu.cn
 */

require_once dirname(__FILE__) . '/feishu_bot.php';

header('Content-Type: application/json; charset=utf-8');

$result = [];

// 0. 确认 feishu_bot.php 是否正确加载
$result['feishu_bot_loaded'] = function_exists('sendFeishuText');
$result['feishu_bot_file_exists'] = file_exists(dirname(__FILE__) . '/feishu_bot.php');
$result['feishu_bot_file_size'] = filesize(dirname(__FILE__) . '/feishu_bot.php');

// 1. 基础环境检查
$result['php_version'] = PHP_VERSION;
$result['curl_available'] = function_exists('curl_init');
$result['allow_url_fopen'] = ini_get('allow_url_fopen') ? true : false;
$result['webhook'] = defined('FEISHU_BOT_WEBHOOK') ? FEISHU_BOT_WEBHOOK : '常量未定义';

if (!function_exists('sendFeishuText')) {
    $result['success'] = false;
    $result['message'] = 'feishu_bot.php 未正确加载，请检查该文件是否已上传到服务器、文件内容是否完整';
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 2. 发送测试文本消息
$testText = "🔔 测试消息\n来自管理后台飞书机器人测试脚本\n时间：" . date('Y-m-d H:i:s') . "\nIP：" . feishuBotClientIp();
$resp = sendFeishuText($testText);

if ($resp === false) {
    $result['success'] = false;
    $result['message'] = '发送失败（curl/file_get_contents 均不可用或请求返回 false）';
} else {
    $body = @json_decode($resp['body'], true);
    $result['success'] = isset($body['code']) && $body['code'] === 0;
    $result['http_code'] = $resp['code'];
    $result['response_body'] = $resp['body'];
    if (!$result['success']) {
        $result['message'] = '飞书接口返回错误：' . ($body['msg'] ?? $resp['body']);
    } else {
        $result['message'] = '测试消息已发送，请查看飞书群聊';
    }
}

// 3. 发送测试登录通知
$loginResp = notifyAdminLogin('测试用户', 'ou_test_open_id_123456', 'feishu', [
    'ip' => feishuBotClientIp(),
    'ua' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'test'
]);
$result['login_notify_sent'] = ($loginResp !== false);

// 4. 发送测试新闻变更通知
$oldNews = [
    ['id' => 1, 'title' => '旧标题一', 'content' => '旧内容', 'status' => 'published']
];
$newNews = [
    ['id' => 1, 'title' => '修改后的标题一', 'content' => '新内容', 'status' => 'published'],
    ['id' => 2, 'title' => '新增标题二', 'content' => '新内容', 'status' => 'published']
];
$newsResp = notifyNewsChanged('测试操作人', 'ou_test_operator', $oldNews, $newNews);
$result['news_notify_sent'] = ($newsResp !== false);

// 输出结果
$result['time'] = date('Y-m-d H:i:s');
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
