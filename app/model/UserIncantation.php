<?php

namespace app\model;

use think\Model;

class UserIncantation extends Model
{

    protected $name = 'user_incantation';


    /**
     * 获取用户咒语列表
     * @param int $userId 用户ID
     * @param int $page 当前页码
     * @param int $limit 每页数量
     * @return array
     */
    public function getIncantationList($userId, $page = 1, $limit = 20)
    {
        $query = $this
            ->where(['user_id' => $userId, 'type' => 2])
            ->order('create_time', 'desc');

        // 获取总记录数
        $totalCount = $query->count();
        // 获取当前页数据
        $list = $query->page($page, $limit)->select();

        return [
            'total_count' => $totalCount,
            'total_pages' => ceil($totalCount / $limit),
            'data' => $list,
        ];
    }

    /**
     * 添加用户咒语
     * @param int $userId 用户ID
     * @param string $title 标题
     * @param string $content 内容
     * @return int
     */
    public function addUserContent($userId, $title, $content)
    {
        return $this->insertGetId([
            'user_id' => $userId,
            'title' => $title,
            'content' => $content,
            'type' => 2,
            'create_time' => time()
        ]);
    }
}
