<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

use fast\Rsa;
use think\facade\Route;


Route::get('think', function () {
    return 'hello,ThinkPHP6!';
});

Route::get('hello/:name', 'index/hello');



// API分组，应用加密中间件
Route::group('api/V1', function () {
    // 显式路由：使用完整的命名空间路径
    Route::post('decrypt', 'app\controller\v1\TestController@decrypt')->middleware('app\middleware\SimpleAuth');
    Route::post('encryption', 'app\controller\v1\TestController@encryption');
    Route::post('guestLogin', 'app\controller\v1\Login@guestLogin')->middleware('app\middleware\SimpleAuth');
    Route::post('getUserinfo', 'app\controller\v1\Login@getUserinfo'); // 获取用户信息
    Route::post('chat', 'app\controller\v1\OpenRouterController@chat'); // 仅允许 POST 请求
    Route::get('test-singleton', 'app\controller\v1\OpenRouterController@testSingleton'); // 单例验证接口
    Route::get('tagList', 'app\controller\v1\ChatController@tagList'); // AI角色标签列表接口
    Route::post('upload', 'app\controller\v1\Upload@upload'); // 上传文件接口
    Route::post('createRole', 'app\controller\v1\ChatController@createRole')->middleware(\app\middleware\JwtAuthMiddleware::class); //获取个人信息; // 创建角色接口

    Route::post('upload', 'app\controller\v1\Upload@upload'); // 上传文件接口
    Route::get('getUserTagList', 'app\controller\v1\UserController@getUserTagList'); // 获取用户标签列表接口
    Route::post('editInfo', 'app\controller\v1\UserController@editInfo'); // 更新用户信息接口
    Route::post('savePresume', 'app\controller\v1\ChatController@savePresume'); // 添加用户设定
    Route::get('viewPresume', 'app\controller\v1\ChatController@viewPresume'); // 查看用户设定
    Route::post('addRoleEvent', 'app\controller\v1\ChatController@addRoleEvent'); // 角色新增事件
    Route::post('addRoleStats', 'app\controller\v1\ChatController@addRoleStats'); // 角色编辑状态栏 
    Route::post('chatWithRole', 'app\controller\v1\ChatController@chatWithRole'); // 与角色聊天接口
    Route::get('text', 'app\controller\v1\ChatController@text'); // 测试接口
    Route::post('innerThought', 'app\controller\v1\ChatController@innerThought'); // 角色内心想法生成
    Route::post('memorySummary', 'app\controller\v1\ChatController@memorySummary'); // 角色记忆总结
    Route::get('chatHistoryList', 'app\controller\v1\ChatController@chatHistory'); // 角色聊天记录
    Route::get('roleList', 'app\controller\v1\ChatController@roleList'); // 角色列表
    Route::get('roleInfo', 'app\controller\v1\ChatController@roleInfo'); // 角色详情
    Route::post('editRole', 'app\controller\v1\ChatController@editRole'); // 编辑角色
    Route::get('getRoleEventList', 'app\controller\v1\ChatController@getRoleEventList'); // 角色事件列表
    Route::get('getDailySummaryList', 'app\controller\v1\ChatController@getDailySummaryList'); // 角色专属日记列表



    Route::get('getRoleHeartList', 'app\controller\v1\ChatController@getRoleHeartList'); // 角色心声列表
    Route::get('readMessage', 'app\controller\v1\ChatController@readMessage'); // 读取消息

    Route::post('saveCollection', 'app\controller\v1\CollectionController@saveCollection'); // 切换角色收藏状态
    Route::get('getUserCollections', 'app\controller\v1\CollectionController@getUserCollections'); // 获取用户收藏列表
    Route::get('chatRoleUserList', 'app\controller\v1\ChatController@chatRoleUserList'); //用户最近聊天列表
    Route::delete('chatRoleUser/:chat_id', 'app\controller\v1\ChatController@delChatRoleUser'); //删除用户最近聊天记录
    Route::get('messageList', 'app\controller\v1\Index@messageList'); // 获取用户消息列表
    Route::post('messageRead', 'app\controller\v1\Index@messageRead'); // 标记消息为已读
    Route::get('myRoleList', 'app\controller\v1\ChatController@myRoleList'); // 获取用户角色列表
    Route::delete('roles/:role_id', 'app\controller\v1\ChatController@delRole'); // 删除角色
    Route::delete('chatHistory/:role_id', 'app\controller\v1\ChatController@delChatHistory'); // 删除角色聊天记录


    Route::post('signIn', 'app\controller\v1\UserController@signIn'); // 用户签到
    Route::get('diamondDetails', 'app\controller\v1\UserController@diamondDetails'); // 获取用户钻石明细
    Route::post('toggleFollow', 'app\controller\v1\UserController@toggleFollow'); // 关注/取消关注角色
    Route::get('getFollowList', 'app\controller\v1\UserController@getFollowList'); // 获取用户关注列表
    Route::get('getFansList', 'app\controller\v1\UserController@getFansList'); // 获取角色粉丝列表

    // 任务系统相关路由
    Route::get('getTaskList', 'app\controller\v1\UserController@getTaskList'); // 获取任务列表
    Route::post('claimTaskReward', 'app\controller\v1\UserController@claimTaskReward'); // 领取任务奖励

    // 邀请奖励相关路由
    Route::post('invitationReward', 'app\controller\v1\UserController@invitationReward'); // 邀请奖励
    Route::get('chatModeList', 'app\controller\v1\ChatController@chatModeList'); // 获取AI角色聊天模式列表接口
    Route::post('editChatHistory', 'app\controller\v1\ChatController@editChatHistory'); // 编辑聊天记录
    Route::post('createRoleDraft', 'app\controller\v1\ChatController@createRoleDraft'); // 创建角色草稿
    Route::get('roleDraftInfo', 'app\controller\v1\ChatController@roleDraftInfo'); // 草稿详情
    Route::post('saveIncantation', 'app\controller\v1\UserController@saveIncantation'); // 保存咒语
    Route::get('getIncantationList', 'app\controller\v1\UserController@getIncantationList'); // 获取咒语列表
    Route::post('chatHistoryCollect', 'app\controller\v1\CollectionController@chatHistoryCollect'); // 收藏聊天记录
    Route::get('getUserChatCollections', 'app\controller\v1\CollectionController@getUserChatCollections'); // 获取用户收藏列表
    Route::get('getMemoryCapsuleList', 'app\controller\v1\MemoryController@getMemoryCapsuleList'); // 获取记忆胶囊列表
    Route::delete('memory/:memory_id', 'app\controller\v1\MemoryController@delMemoryCapsule'); //删除记忆胶囊
    Route::post('editMemoryCapsule', 'app\controller\v1\MemoryController@editMemoryCapsule'); //编辑记忆胶囊
});
