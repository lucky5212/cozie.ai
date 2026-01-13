<?php

namespace app\controller\v1;

namespace app\controller\v1;

use app\BaseController;
use app\common\JwtAuth;
use app\model\RoleMemory;

class MemoryController extends BaseController
{


    // 获取角色记忆胶囊列表

    public function getMemoryCapsuleList()
    {

        // 验证用户身份
        $token = JwtAuth::decodeToken($this->request->header('Access-Token'));
        if (!$token) {
            return json([
                'code' => 401,
                'msg' => '未授权'
            ]);
        }
        $userId = $token['uid'];
        // 获取角色ID
        $roleId = $this->request->param('role_id');
        if (!$roleId) {
            return json([
                'code' => 400,
                'msg' => '角色ID不能为空'
            ]);
        }

        // 获取分页参数
        $page = max(1, (int)$this->request->get('page', 1));
        $limit = max(1, min(50, (int)$this->request->get('limit', 20)));
        $type = $this->request->get('type', '1'); // 1= 每日记忆,2=每月记忆;
        if (!in_array($type, ['1', '2'])) {
            return json([
                'code' => 400,
                'msg' => '记忆类型错误'
            ]);
        }
        // 调用模型方法获取记忆胶囊列表
        $sub_category = $type == 1 ? "memory" : "daily_summary";
        $memoryModel = new RoleMemory();
        $result = $memoryModel->getMemoryCapsuleList($userId, $roleId, $sub_category, $page, $limit);
        return json([
            'code' => 200,
            'msg' => '请求成功',
            'data' => [
                'current_page' => (int)$page,  // 当前页码
                'page_size' => (int)$limit,     // 每页记录数
                'total_count' => $result['total_count'],   // 总记录数
                'total_pages' => $result['total_pages'],   // 总页数
                'data' => $result['data'],  // 聊天记录数据
            ]
        ]);
    }

    // 删除记忆胶囊
    public function delMemoryCapsule()
    {
        // 验证用户身份
        $token = JwtAuth::decodeToken($this->request->header('Access-Token'));
        if (!$token) {
            return json([
                'code' => 401,
                'msg' => '未授权'
            ]);
        }
        $userId = $token['uid'];
        // 获取记忆胶囊ID
        $memoryId = $this->request->param('memory_id');
        if (!$memoryId) {
            return json([
                'code' => 400,
                'msg' => '记忆胶囊ID不能为空'
            ]);
        }
        // 调用模型方法删除记忆胶囊
        $memoryModel = new RoleMemory();
        $result = $memoryModel->delMemoryCapsule($userId, $memoryId);
        return json([
            'code'    => 200,
            'msg' => '删除成功',
            'data'    => []
        ]);
    }

    // 编辑记忆胶囊
    public function editMemoryCapsule()
    {
        // 验证用户身份
        $token = JwtAuth::decodeToken($this->request->header('Access-Token'));
        if (!$token) {
            return json([
                'code' => 401,
                'msg' => '未授权'
            ]);
        }
        $userId = $token['uid'];
        // 获取记忆胶囊ID
        $memoryId = $this->request->param('memory_id');
        if (!$memoryId) {
            return json([
                'code' => 400,
                'msg' => '记忆胶囊ID不能为空'
            ]);
        }
        // 获取编辑数据
        $data = $this->request->param('content');
        if (!$data) {
            return json([
                'code' => 400,
                'msg' => '编辑数据不能为空'
            ]);
        }
        if (strlen($data) > 10000) {
            return json([
                'code' => 400,
                'msg' => '编辑数据不能超过2000个字符'
            ]);
        }
        // 调用模型方法编辑记忆胶囊
        $memoryModel = new RoleMemory();
        $memoryModel->editMemoryCapsule($userId, $memoryId, $data);
        return json([
            'code'    => 200,
            'msg' => '编辑成功',
            'data'    => []
        ]);
    }
}
