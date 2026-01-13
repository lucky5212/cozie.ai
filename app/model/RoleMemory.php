<?php

namespace app\model;

use think\Model;

class RoleMemory extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $name = 'role_memory';

    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;

    /**
     * 保存记忆数据
     * @param int $userId 用户ID
     * @param int $roleId 角色ID
     * @param array $memories 记忆数据
     * @return bool
     */
    public function saveMemories($userId, $roleId, $memories, $message_id = 0)
    {
        // 开始事务
        return $this->transaction(function () use ($userId, $roleId, $memories, $message_id) {
            // 先删除该用户和角色的旧记忆
            // $this->where(['user_id' => $userId, 'role_id' => $roleId])->delete();

            // 保存新记忆
            $data = [];
            foreach ($memories as $memory) {
                $data[] = [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'content' => $memory['content'],
                    'category' => $memory['category'],
                    'sub_category' => $memory['sub_category'],
                    'create_time' => time(),
                    'update_time' => time(),
                    'chat_id' => $message_id,
                ];
            }
            // 批量插入记忆数据
            $this->saveAll($data);
            return true;
        });
    }


    /**
     * 获取用户和角色的专属日记
     * @param int $userId 用户ID
     * @param int $roleId 角色ID
     * @param int $page 页码
     * @param int $limit 每页记录数
     * @return array
     */
    public function DailyDiaryList($userId, $roleId, $page = 1, $limit = 10)
    {
        $count = $this->where(['user_id' => $userId, 'role_id' => $roleId, 'sub_category' => 'daily_diary'])->count();
        $result =   $this->where(['user_id' => $userId, 'role_id' => $roleId, 'sub_category' => 'daily_diary', 'status' => 1])
            ->order('create_time', 'asc')
            ->page($page, $limit)
            ->select()
            ->toArray();

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
    public function getMemories($userId, $roleId)
    {
        return $this->where(['user_id' => $userId, 'role_id' => $roleId])
            ->order('create_time', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 获取用户和角色的当日记忆数据
     * @param int $userId 用户ID
     * @param int $roleId 角色ID
     * @return array
     */
    public function getTodayMemories($userId, $roleId)
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
    /**
     * 删除用户和角色的记忆数据
     * @param int $userId 用户ID
     * @param int $roleId 角色ID
     * @param string $message_id 消息ID
     * @return bool
     */
    public function deleteMemories($userId, $roleId, $message_id)
    {
        return $this->where(['user_id' => $userId, 'role_id' => $roleId, 'chat_id' => $message_id])->delete();
    }


    /**
     * 获取用户和角色的记忆胶囊列表
     * @param int $userId 用户ID
     * @param int $roleId 角色ID
     * @param int $type 记忆类型
     * @param int $page 页码
     * @param int $limit 每页记录数
     * @return array
     */
    public function getMemoryCapsuleList($userId, $roleId, $sub_category, $page = 1, $limit = 10)
    {
        $count = $this->where(['user_id' => $userId, 'role_id' => $roleId, 'status' => 1, 'sub_category' => $sub_category])
            ->count();
        $result = $this->where(['user_id' => $userId, 'role_id' => $roleId, 'status' => 1, 'sub_category' => $sub_category])
            ->order('create_time', 'asc')
            ->page($page, $limit)
            ->field('id,content,create_time')
            ->select()
            ->toArray();
        if ($sub_category == 'memory') {
            foreach ($result as $key => $item) {
                $result[$key]['create_time'] = date('Y-m-d', strtotime($item['create_time']));
            }
        } elseif ($sub_category == 'daily_summary') {
            foreach ($result as $key => $item) {
                $result[$key]['create_time'] = date('Y-m', strtotime($item['create_time']));
            }
        }
        return [
            'total_count' => $count, // 总记录数
            'total_pages' => ceil($count / $limit), // 最后一页的页码
            'data' => $result, // 当前页的数据
        ];
    }

    // 删除记忆胶囊
    public function delMemoryCapsule($userId, $memoryId)
    {
        return $this->where(['user_id' => $userId, 'id' => $memoryId])->delete();
    }

    // 编辑记忆胶囊
    public function editMemoryCapsule($userId, $memoryId, $data)
    {
        return $this->where(['user_id' => $userId, 'id' => $memoryId])->update(['content' => $data]);
    }
}
