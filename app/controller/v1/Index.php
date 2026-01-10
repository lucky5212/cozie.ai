<?php

namespace app\controller\v1;

use app\common\JwtAuth;
use app\model\SystemMessage;
use think\facade\Request;
use app\BaseController;

class Index extends BaseController
{
    /**
     * 测试接口：index
     * @return \think\response\Json
     */
    public function index()
    {
        return json([
            'code' => 200,
            'msg'  => '测试接口调用成功',
            'data' => [
                'time' => date('Y-m-d H:i:s'),
                'method' => Request::method(),
                'params' => Request::param()
            ]
        ]);
    }


    // 获取用户消息列表
    public function messageList()
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
        $limit = $this->request->param('limit', 10);
        $page = $this->request->param('page', 1);
        $offset = ($page - 1) * $limit;
        $systemMessageModel = new SystemMessage();
        $result = $systemMessageModel->messageList($userId, $limit, $offset);
        return json([
            'code' => 200,
            'msg' => '请求成功',
            'data' => [
                'current_page' => (int)$page,  // 当前页码
                'page_size' => (int)$limit,     // 每页记录数
                'total_count' => $result['total_count'], // 总记录数
                'total_pages' => ceil($result['total_count'] / $limit), // 最后一页的页码
                'data' => $result['data'], // 当前页的数据
            ]
        ]);
    }
    // 标记消息为已读
    public function messageRead()
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
        $messageId = $this->request->param('message_id');
        $systemMessageModel = new SystemMessage();
        $systemMessageModel->where(['id' => $messageId, 'user_id' => $userId])->update(['is_read' => 1]);
        return json([
            'code' => 200,
            'msg' => '请求成功',
            'data' => []
        ]);
    }
}
