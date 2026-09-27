<?php
echo "<h3>网络连通性测试</h3>";

// 测试1: cURL 访问飞书
echo "<br><b>=== 测试1: cURL访问飞书Token接口 ===</b><br>";
$ch = curl_init('https://open.feishu.cn/open-apis/authen/v1/oauth2/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, '{"grant_type":"authorization_code","code":"test123"}');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$r = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "HTTP状态码: $code<br>";
if ($err) echo "cURL错误: $err<br>";
echo "有效URL: " . ($info['url'] ?? 'N/A') . "<br>";
echo "重定向次数: " . ($info['redirect_count'] ?? '0') . "<br>";
echo "响应内容(前800字):<br><pre>" . htmlspecialchars(substr($r, 0, 800)) . "</pre>";

// 测试2: 基础配置
echo "<br><b>=== 测试2: PHP基础配置 ===</b><br>";
echo "allow_url_fopen: " . ini_get('allow_url_fopen') . "<br>";
echo "disable_functions: " . ini_get('disable_functions') . "<br>";
echo "PHP版本: " . PHP_VERSION . "<br>";
echo "cURL扩展: " . (extension_loaded('curl') ? '已安装' : '未安装') . "<br>";
echo "OpenSSL扩展: " . (extension_loaded('openssl') ? '已安装' : '未安装') . "<br>";

// 测试3: 用file_get_contents试一次
echo "<br><b>=== 测试3: file_get_contents方式 ===</b><br>";
$ctx = stream_context_create(['http' => [
    'method' => 'POST',
    'header' => "Content-Type: application/json\r\n",
    'content' => '{"test":1}',
    'timeout' => 10,
    'ignore_errors' => true,
    'ssl' => ['verify_peer' => false],
]]);
$r2 = @file_get_contents('https://open.feishu.cn/open-apis/authen/v1/oauth2_token', false, $ctx);
if ($r2 === false) {
    echo "file_get_contents 失败: " . (error_get_last()['message'] ?? 'unknown') . "<br>";
} else {
    echo "file_get_contents 成功! 响应:<br><pre>" . htmlspecialchars(substr($r2, 0, 500)) . "</pre>";
}
?>
