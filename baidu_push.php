<?php
// 百度站长平台 API 自动推送脚本
// 使用方法：上传到服务器后访问 https://www.jiaodaodadui.com.cn/baidu_push.php

// 配置信息
$site = 'https://www.jiaodaodadui.com.cn';
$token = 'Cu4Vb8njvn4q86SR';
$api = "http://data.zz.baidu.com/urls?site={$site}&token={$token}";

// 需要推送的URL列表
$urls = array(
    'https://www.jiaodaodadui.com.cn/',
    'https://www.jiaodaodadui.com.cn/index.html',
);

// 初始化cURL
$ch = curl_init();

// 设置cURL选项
$options = array(
    CURLOPT_URL => $api,
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => implode("\n", $urls),
    CURLOPT_HTTPHEADER => array('Content-Type: text/plain'),
);

curl_setopt_array($ch, $options);

// 执行请求
$result = curl_exec($ch);

// 检查错误
if (curl_errno($ch)) {
    $result = 'Error: ' . curl_error($ch);
}

// 关闭cURL
curl_close($ch);

// 输出结果
header('Content-Type: text/plain; charset=utf-8');
echo "=== 百度推送结果 ===\n";
echo "时间: " . date('Y-m-d H:i:s') . "\n";
echo "站点: {$site}\n";
echo "结果: {$result}\n";
?>
