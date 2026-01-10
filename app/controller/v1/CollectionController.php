<?php

namespace app\controller\v1;

use think\Controller;
use app\model\RoleCollection;
use app\model\Role;
use app\BaseController;
use app\common\JwtAuth;

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
            'code'    => 200,
            'message' => '获取收藏列表成功',
            'data'    => [
                'list'         => $result['list'],
                'total'        => $result['total'],
                'page'         => $result['page'],
                'limit'        => $result['limit'],
                'total_pages'  => $result['totalPages']
            ]
        ]);
    }
}
