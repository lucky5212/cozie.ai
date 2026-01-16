<?php
// 引入TP8的入口文件
require __DIR__ . '/index.php';

// 模拟POST请求（覆盖当前请求方法和参数）
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['username'] = 'test';
$_POST['password'] = '123456';

// 手动触发路由匹配（调用/api/v1/guestLogin）
$response = think\facade\Route::dispatch('/api/v1/text', 'GET');
// 输出响应结果
var_dump($response->getContent());