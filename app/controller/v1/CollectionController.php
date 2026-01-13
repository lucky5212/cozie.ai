<?php

namespace app\controller\v1;

use think\Controller;
use app\model\RoleCollection;
use app\model\Role;
use app\BaseController;
use app\common\JwtAuth;
use app\model\RoleChatCollect;
use think\facade\Db;

class CollectionController extends BaseController
{
    /**
     * 切换角色收藏状态
     * @return json
     */
    public function saveCollection()
    {
        // 获取用户ID（假设通过中间件获取，这里模拟）

        $token = JwtAuth::decodeToken($this->request->header('Access-Token'));
        if (!$token) {
            return json([
                'code' => 401,
                'msg' => '未授权'
            ]);
        }
        $userId = $token['uid'];
        // 获取角色ID
        $roleId = (int)$this->request->post('role_id');
        if (empty($roleId)) {
            return json([
                'code'    => 400,
                'msg' => '角色ID不能为空'
            ]);
        }

        // 验证角色是否存在
        $role = Role::find($roleId);
        if (!$role) {
            return json([
                'code'    => 404,
                'msg' => '角色不存在'
            ]);
        }

        // 调用模型方法切换收藏状态
        $collectionModel = new RoleCollection();
        $result = $collectionModel->saveCollection($userId, $roleId);

        if ($result['success']) {
            return json([
                'code'    => 200,
                'msg' => $result['message'],
                'data'    => []
            ]);
        } else {
            return json([
                'code'    => 500,
                'msg' => $result['message']
            ]);
        }
    }

    /**
     * 获取用户收藏的角色列表
     * @return json
     */
    public function getUserCollections()
    {
        // 获取用户ID
        $token = JwtAuth::decodeToken($this->request->header('Access-Token'));
        if (!$token) {
            return json([
                'code' => 401,
                'msg' => '未授权'
            ]);
        }
        $userId = $token['uid'];

        // 获取分页参数
        $page = max(1, (int)$this->request->get('page', 1));
        $limit = max(1, min(50, (int)$this->request->get('limit', 20)));

        // 调用模型方法获取收藏列表
        $collectionModel = new RoleCollection();
        $result = $collectionModel->getUserCollections($userId, $page, $limit);

        return json([
            'code' => 200,
            'msg' => '请求成功',
            'data' => [
                'current_page' => (int)$page,  // 当前页码
                'page_size' => (int)$limit,     // 每页记录数
                'total_count' => $result['total'],   // 总记录数
                'total_pages' => $result['totalPages'],   // 总页数
                'result' => $result['list'],  // 聊天记录数据
            ]
        ]);
    }

    /**
     * 切换聊天记录收藏状态
     * @return json
     */
    public function chatHistoryCollect()
    {
        // 获取用户ID
        $token = JwtAuth::decodeToken($this->request->header('Access-Token'));
        if (!$token) {
            return json([
                'code' => 401,
                'msg' => '未授权'
            ]);
        }
        $messageId = (int)$this->request->post('message_id');
        $userId = $token['uid'];
        if (empty($messageId)) {
            return json([
                'code'    => 400,
                'msg' => '消息ID不能为空'
            ]);
        }
        $result = Db::name('role_chat_history')
            ->where('user_id', $userId)
            ->where('id', $messageId)
            ->field('id,role_id,question,answer')
            ->find();
        if (!$result) {
            return json([
                'code'    => 404,
                'msg' => '聊天记录不存在'
            ]);
        }
        // 调用模型方法切换收藏状态
        $collectionModel = new RoleChatCollect();
        $result = $collectionModel->saveChatHistoryCollect($userId, $result['role_id'], $messageId, $result['question'], $result['answer']);

        if ($result['success']) {
            return json([
                'code'    => 200,
                'msg' => $result['message'],
                'data'    => []
            ]);
        } else {
            return json([
                'code'    => 500,
                'msg' => $result['message']
            ]);
        }
    }

    /**
     * 获取用户收藏的聊天记录列表
     * @return json
     */
    public function getUserChatCollections()
    {
        // 获取用户ID
        $token = JwtAuth::decodeToken($this->request->header('Access-Token'));
        if (!$token) {
            return json([
                'code' => 401,
                'msg' => '未授权'
            ]);
        }
        $userId = $token['uid'];

        // 获取分页参数
        $page = max(1, (int)$this->request->get('page', 1));
        $limit = max(1, min(50, (int)$this->request->get('limit', 20)));

        // 调用模型方法获取收藏列表
        $collectionModel = new RoleChatCollect();
        $result = $collectionModel->getUserChatCollections($userId, $page, $limit);

        return json([
            'code' => 200,
            'msg' => '请求成功',
            'data' => [
                'current_page' => (int)$page,  // 当前页码
                'page_size' => (int)$limit,     // 每页记录数
                'total_count' => $result['total_count'],   // 总记录数
                'total_pages' => $result['total_pages'],   // 总页数
                'result' => $result['data'],  // 聊天记录数据
            ]
        ]);
    }
}
