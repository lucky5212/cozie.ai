<?php
// test_proxy.php - 测试代理配置
require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

try {
    echo "Testing proxy configuration...\n";
    
    // 测试代理地址
    $proxy = 'http://127.0.0.1:7890';
    
    echo "Using proxy: $proxy\n";
    
    // 创建 Symfony HttpClient
    $symfonyHttpClient = HttpClient::create([
        'timeout' => 30,
        'proxy' => $proxy,
        'verify_peer' => false, // 跳过 SSL 验证（测试用）
    ]);
    
    $httpClient = new Psr18Client($symfonyHttpClient);
    
    echo "Testing connection to openrouter.ai...\n";
    
    // 创建一个简单的请求
    $request = new \Nyholm\Psr7\Request('GET', 'https://openrouter.ai');
    
    // 发送请求
    $response = $httpClient->sendRequest($request);
    
    echo "Status code: " . $response->getStatusCode() . "\n";
    echo "Headers: " . print_r($response->getHeaders(), true);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}