<?php
// config/openrouter.php
return [
    // OpenRouter API Key
    'api_key' => env('OPENROUTER.OPENROUTER_API_KEY', ''),
    // OpenRouter 基础地址
    'base_url' => env('OPENROUTER.OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
    // 自定义请求头（OpenRouter 要求的 Referer、Title 等）
    'extra_headers' => [
        'HTTP-Referer' => env('OPENROUTER.OPENROUTER_REFERER', ''),
        'X-Title' => env('OPENROUTER.OPENROUTER_TITLE', ''),
        'Content-Type' => 'application/json',
    ],
    // HTTP 客户端配置（超时、代理等）
    'http_client' => [
        'timeout' => env('OPENROUTER.OPENROUTER_TIMEOUT', 30),
    ],
];