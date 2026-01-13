<?php

namespace app\model;

use think\Model;

class UserFollow extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $name = 'user_follow';

    // 定义时间戳字段名
    protected $createTime = 'create_time';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;

    /**
     * 切换用户关注状态
     * @param int $userId 用户ID
     * @param int $followUserId 关注的角色ID
     * @return array 操作结果
     */
    public function toggleFollow($userId, $followUserId)
    {
        // 查找关注记录
        $followRecord = $this->where([
            'user_id' => $userId,
            'follow_user_id' => $followUserId
        ])->find();

        if (!$followRecord) {
            // 不存在记录，创建关注记录
            $result = $this->save([
                'user_id' => $userId,
                'follow_user_id' => $followUserId,
                'status' => 1,
                'create_time' => time()
            ]);

            return [
                'success' => $result !== false,
                'status' => 1,
                'message' => '关注成功'
            ];
        } else {
            // 存在记录，切换关注状态
            $newStatus = $followRecord['status'] == 1 ? 0 : 1;
            $result = $followRecord->save(['status' => $newStatus]);

            return [
                'success' => $result !== false,
                'status' => $newStatus,
                'message' => $newStatus == 1 ? '关注成功' : '取消关注成功'
            ];
        }
    }

    /**
     * 检查用户是否关注了某个角色
     * @param int $userId 用户ID
     * @param int $followUserId 关注的角色ID
     * @return bool 是否已关注
     */
    public function isFollowing($userId, $followUserId)
    {
        $result = $this->where([
            'user_id' => $userId,
            'follow_user_id' => $followUserId,
            'status' => 1
        ])->find();

        return $result !== null;
    }

    /**
     * 获取用户关注列表
     * @param int $userId 用户ID
     * @param int $page 页码
     * @param int $limit 每页条数
     * @return array 关注列表
     */
    public function getFollowList($userId, $page = 1, $limit = 20)
    {

        $data = $this->alias('uf')
            ->join('role r', 'uf.follow_user_id = r.id', 'LEFT')
            ->where('uf.user_id', $userId)
            ->where('uf.status', 1)
            ->field('uf.id, uf.follow_user_id, uf.create_time, r.name, r.avatar_url')
            ->order('uf.create_time', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $total_count = $this->where([
            'user_id' => $userId,
            'status' => 1
        ])->count();

        foreach ($data as $key => $value) {
            $data[$key]['avatar_url'] = cdnurl($value['avatar_url']);
        }
        return [
            'data' => $data,
            'total_count' => $total_count,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total_count / $limit)
        ];
    }

    /**
     * 获取角色粉丝列表
     * @param int $followUserId 角色ID
     * @param int $page 页码
     * @param int $limit 每页条数
     * @return array 粉丝列表
     */
    public function getFansList($followUserId, $page = 1, $limit = 20)
    {
        $data = $this->alias('uf')
            ->join('user u', 'uf.user_id = u.id', 'LEFT')
            ->where('uf.follow_user_id', $followUserId)
            ->where('uf.status', 1)
            ->field('uf.id, uf.user_id, uf.create_time, u.nickname, u.avatar')
            ->order('uf.create_time', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $total_count = $this->where([
            'follow_user_id' => $followUserId,
            'status' => 1
        ])->count();

        foreach ($data as $key => $value) {
            $data[$key]['avatar_url'] = cdnurl($value['avatar']);
        }
        return [
            'data' => $data,
            'total_count' => $total_count,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total_count / $limit)
        ];
    }
}
