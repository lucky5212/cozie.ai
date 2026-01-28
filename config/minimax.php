<?php
// config/minimax.php
return [
    // MiniMax API Key
    'api_key' => env('MINIMAX.MINIMAX_API_KEY', ''),
    // MiniMax Group ID
    'group_id' => env('MINIMAX.MINIMAX_GROUP_ID', ''),
    // MiniMax TTS 基础地址
    'tts_base_url' => env('MINIMAX.MINIMAX_TTS_BASE_URL', 'https://api.minimax.chat/v1/text_to_speech'),
    // 默认音色
    'default_voice' => env('MINIMAX.MINIMAX_DEFAULT_VOICE', 'female-tianmei'),
    // 默认语速 (0.5-2.0)
    'default_speed' => env('MINIMAX.MINIMAX_DEFAULT_SPEED', 1.0),
    // 默认音调 (-12 到 12)
    'default_pitch' => env('MINIMAX.MINIMAX_DEFAULT_PITCH', 0),
    // 支持的音色列表
    'voices' => [
        'female-tianmei' => '女声-甜美',
        'female-yujie' => '女声-御姐',
        'female-shaonv' => '女声-少女',
        'male-qinggan' => '男声-情感',
        'male-chunhou' => '男声-醇厚',
        'male-qingshu' => '男声-轻熟',
    ],
    // HTTP 客户端配置
    'http_client' => [
        'timeout' => env('MINIMAX.MINIMAX_TIMEOUT', 30),
    ],
];