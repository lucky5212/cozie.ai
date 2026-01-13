<?php

namespace app\model;

use FFI;
use think\Model;

class RoleChatCollect extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $name = 'role_chat_collect';

    // 定义时间戳字段名
    protected $createTime = 'create_time';
    // 自动写入时间戳
    protected $autoWriteTimestamp = true;

    // // 定义字段类型
    // protected $type = [
    //     'role_id'      => 'integer',
    //     'user_id'      => 'integer',
    //     'chat_history_id'  => 'integer',
    //     'create_time'  => 'integer',
    // ];

    /**
     * 切换收藏状态
     * @param int $userId 用户ID
     * @param int $chatHistoryId 聊天记录ID
     * @return array 操作结果
     */
    public function saveChatHistoryCollect($userId, $roleId, $chatHistoryId, $question, $answer)
    {
        // 查找收藏记录
        $record = $this->where([
            'user_id' => $userId,
            'role_id' => $roleId,
            'chat_history_id' => $chatHistoryId
        ])->find();

        // 事务处理
        $this->startTrans();
        try {
            if (!$record) {
                // 记录不存在，创建收藏（status=1）
                $this->create([
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'chat_history_id' => $chatHistoryId,
                    'question' => $question,
                    'answer' => $answer,
                ]);
                $this->commit();
                return [
                    'success'    => true,
                    'status'     => 1,
                    'message'    => '收藏成功'
                ];
            } else {
                // 记录存在，切换状态
                if ($record['status'] == 1) {
                    // 当前已收藏，取消收藏（status=0）
                    $record->save(['status' => 0, 'question' => $question, 'answer' => $answer]);
                    $this->commit();
                    return [
                        'success'    => true,
                        'status'     => 0,
                        'message'    => '取消收藏成功'
                    ];
                } else {
                    // 当前已取消，重新收藏（status=1）
                    $record->save(['status' => 1]);
                    $this->commit();
                    return [
                        'success'    => true,
                        'status'     => 1,
                        'message'    => '收藏成功'
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->rollback();
            return [
                'success'    => false,
                'message'    => '操作失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 检查用户是否已收藏该聊天记录
     * @param int $userId 用户ID
     * @param int $chatHistoryId 聊天记录ID
     * @return bool 是否已收藏
     */
    public function isCollected($userId, $chatHistoryId)
    {
        return $this->where([
            'user_id' => $userId,
            'chat_history_id' => $chatHistoryId,
            'status'  => 1
        ])->find() ? true : false;
    }

    /**
     * 获取用户收藏的聊天记录列表
     * @param int $userId 用户ID
     * @param int $page 页码
     * @param int $limit 每页条数
     * @return array 收藏列表
     */
    public function getUserChatCollections($userId, $page = 1, $limit = 20)
    {
        // 查询收藏列表，关联聊天记录表、角色表和用户表
        $collectionList = $this->alias('rc')
            ->where([
                'rc.user_id' => $userId,
                'rc.status'  => 1
            ])
            ->join('role', 'rc.role_id = role.id') // 关联角色表
            ->join('user', 'rc.user_id = user.id') // 关联用户表
            ->field([
                'rc.id',
                'rc.role_id',
                'rc.question',
                'rc.answer',
                'rc.create_time',
                'role.name as role_name',
                'role.avatar_url as role_avatar',
                'user.avatar as user_avatar'
            ])
            ->order('rc.create_time', 'desc')
            ->page($page, $limit)
            ->select();
        // 获取总数
        $total = $this->where([
            'user_id' => $userId,
            'status'  => 1
        ])->count();

        foreach ($collectionList as $key => $item) {

            $collectionList[$key]['role_avatar'] = cdnurl($item['role_avatar']);
            $collectionList[$key]['user_avatar'] = cdnurl($item['user_avatar']);
        }

        return [
            'total_count' => $total, // 总记录数
            'total_pages' => ceil($total / $limit), // 最后一页的页码
            'data' => $collectionList, // 当前页的数据
        ];
    }
}
