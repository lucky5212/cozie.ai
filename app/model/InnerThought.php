<?php

namespace app\model;

use think\Model;

class InnerThought extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $name = 'role_chat_history_inner_thought';

    // 定义时间戳字段名
    protected $createTime = 'create_time';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;

    /**
     * 保存记忆数据
     * @param int $userId 用户ID
     * @param int $roleId 角色ID
     * @param array $innerThoughts 内心想法
     * @return bool
     */
    public function saveInnerThoughts($userId, $roleId, $innerThoughts, $historyId)
    {
        // 开始事务
        return $this->transaction(function () use ($userId, $roleId, $innerThoughts, $historyId) {
            $data = [
                'user_id' => $userId,
                'role_id' => $roleId,
                'content' => $innerThoughts,
                'history_id' => $historyId,
                'create_time' => time(),
            ];
            // 批量插入记忆数据
            $this->save($data);
            return true;
        });
    }


    public function RoleHeartList($userId, $roleId, $limit = 10, $offset = 0)
    {
        $result =  $this->where(['user_id' => $userId, 'role_id' => $roleId])
            ->order('create_time', 'desc')
            ->page($offset, $limit)
            ->select()
            ->toArray();
        $count = $this->where(['user_id' => $userId, 'role_id' => $roleId])->count();
        return [
            'total_count' => $count, // 总记录数
            'total_pages' => ceil($count / $limit), // 最后一页的页码
            'data' => $result, // 当前页的数据
        ];
    }

    /**
     * 获取用户和角色的记忆数据
     * @param int $userId 用户ID
     * @param int $roleId 角色ID
     * @return array
     */
    public function getInnerThoughts($userId, $roleId)
    {
        return $this->where(['user_id' => $userId, 'role_id' => $roleId])
            ->order('create_time', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 获取用户和角色的当日内心想法数据
     * @param int $userId 用户ID
     * @param int $roleId 角色ID
     * @return array
     */
    public function getTodayInnerThoughts($userId, $roleId)
    {
        // 获取今日的开始时间戳
        $todayStart = strtotime(date('Y-m-d'));
        // 获取今日的结束时间戳
        $todayEnd = $todayStart + 86400;

        return $this->where(['user_id' => $userId, 'role_id' => $roleId])
            ->where('create_time', '>=', $todayStart)
            ->where('create_time', '<', $todayEnd)
            ->order('create_time', 'asc')
            ->select()
            ->toArray();
    }
}
