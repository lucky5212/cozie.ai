<?php

namespace app\model;

use think\Model;
use think\facade\Db;

class RoleUserChat extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $name = 'role_user_chat';

    // 定义时间戳字段名
    protected $createTime = 'create_time';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;

    /**
     * 关联角色表
     * @return \think\model\relation\BelongsTo
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    /**
     * 获取用户的聊天列表
     * @param int $userId 用户ID
     * @param int $limit 每页条数
     * @param int $page 页码
     * @return array 聊天列表数据
     */
    public function getChatList($userId, $limit = 20, $page = 1)
    {
        // 使用闭包查询最新的一条聊天记录
        $subQuery = DB::name('role_chat_history')
            ->field('user_id, role_id, MAX(id) as max_id')
            ->group('user_id, role_id');

        // 查询聊天列表
        $list = $this->alias('ruc')
            ->where('ruc.user_id', $userId)
            ->where('ruc.status', 1) // 只查询有效记录
            ->join('role r', 'ruc.role_id = r.id') // 关联角色表
            ->join($subQuery->buildSql() . ' t', 'ruc.user_id = t.user_id AND ruc.role_id = t.role_id') // 关联最新聊天记录子查询
            ->join('role_chat_history rch', 't.max_id = rch.id') // 关联聊天记录表获取最新记录内容
            ->field([
                'ruc.id',
                'ruc.user_id',
                'ruc.role_id',
                'ruc.continuous_days',
                'ruc.last_chat_date',
                'r.name as role_name',
                'r.avatar_url',
                'rch.question as last_question',
                'rch.answer as last_answer',
                'rch.create_time as last_chat_time',
                'rch.is_read'
            ])
            ->order('ruc.last_chat_date', 'desc') // 按最后聊天时间倒序
            ->page($page, $limit)
            ->select();

        foreach ($list as $key => $value) {
            $list[$key]['last_chat_time'] = date('Y-m-d H:i:s', $value['last_chat_time']);
            $list[$key]['last_chat_date'] = date('Y-m-d H:i:s', $value['last_chat_date']);
            $list[$key]['avatar_url'] =  cdnurl($value['avatar_url']);
        }

        // 获取总数
        $total = $this->where('user_id', $userId)->where('status', 1)->count();

        return [
            'data' => $list,
            'total_count' => $total,
            'total_pages' => ceil($total / $limit)
        ];
    }

    /**
     * 更新用户的连续聊天天数状态
     * @param int $userId 用户ID
     * @return bool 更新结果
     */
    public function updateChatList($userId)
    {
        // 计算昨天的时间范围
        $yesterdayStart = strtotime('yesterday'); // 昨天00:00:00
        $yesterdayEnd = strtotime('today') - 1; // 昨天23:59:59

        // 查询连续聊天天数大于等于1的记录
        $chatRecords = $this->where([
            'user_id' => $userId,
            'continuous_days' => ['>=', 1],
            'status' => 1
        ])->select();

        if (empty($chatRecords)) {
            return true; // 没有需要更新的记录
        }
        // 事务处理
        $this->startTrans();
        try {
            foreach ($chatRecords as $record) {
                $lastChatDate = $record['last_chat_date'];
                // 判断最后聊天时间是否在昨天
                if ($lastChatDate < $yesterdayStart || $lastChatDate > $yesterdayEnd) {
                    // 昨天没有聊天，将连续聊天天数改为0
                    $this->where('id', $record['id'])->update([
                        'continuous_days' => 0
                    ]);
                }
            }

            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->rollback();
            // 可以记录日志
            // Log::error('更新连续聊天天数失败：' . $e->getMessage());
            return false;
        }
    }
}
