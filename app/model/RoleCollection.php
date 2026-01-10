<?php

namespace app\model;

use think\Model;

class RoleCollection extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $name = 'role_collection';

    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;

    // 定义字段类型
    protected $type = [
        'role_id'      => 'integer',
        'user_id'      => 'integer',
        'status'       => 'integer',
        'create_time'  => 'integer',
        'update_time'  => 'integer',
    ];

    /**
     * 切换收藏状态
     * @param int $userId 用户ID
     * @param int $roleId 角色ID
     * @return array 操作结果
     */
    public function saveCollection($userId, $roleId)
    {
        // 查找收藏记录
        $record = $this->where([
            'user_id' => $userId,
            'role_id' => $roleId
        ])->find();

        // 事务处理
        $this->startTrans();
        try {
            if (!$record) {
                // 记录不存在，创建收藏（status=1）
                $this->create([
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'status'  => 1
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
                    $record->save(['status' => 0]);
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
     * 获取用户收藏的角色列表
     * @param int $userId 用户ID
     * @param int $page 页码
     * @param int $limit 每页条数
     * @return array 收藏列表
     */
    public function getUserCollections($userId, $page = 1, $limit = 20)
    {
        // 查询收藏列表，关联角色表、聊天关系表
        $collectionList = $this->alias('rc')
            ->where([
                'rc.user_id' => $userId,
                'rc.status'  => 1
            ])
            ->join('role r', 'rc.role_id = r.id') // 关联角色表
            ->join('role_user_chat ruc', 'rc.user_id = ruc.user_id AND rc.role_id = ruc.role_id', 'left') // 关联用户角色聊天表
            ->field([
                'rc.id as collection_id',
                'rc.role_id',
                'rc.user_id',
                'rc.create_time',
                'r.name',
                'r.avatar_url',
                'r.age',
                'r.occupation as identity',
                'r.chat_num',
                'ruc.continuous_days',
                'ruc.favorability'
            ])
            ->order('rc.create_time', 'desc')
            ->page($page, $limit)
            ->select();
        // 获取好感度级别数据
        $favorabilityLevels = RoleFavorability::order('num', 'asc')->select();
        $levelMapping = [];
        foreach ($favorabilityLevels as $level) {
            $levelMapping[$level['id']] = $level;
        }

        // 处理每个收藏记录的好感度级别
        foreach ($collectionList as &$item) {
            // 获取用户对该角色的好感度
            $favorability = $item['favorability'] ?? 0;

            // 匹配好感度级别
            $matchedLevel = null;
            foreach ($favorabilityLevels as $level) {
                if ($favorability >= $level['num']) {
                    $matchedLevel = $level;
                } else {
                    break;
                }
            }

            // 设置好感度级别信息
            if ($matchedLevel) {
                $item['favorability_level'] = [
                    'id'       => $matchedLevel['id'],
                    'name'     => $matchedLevel['name'],
                    'num'      => $matchedLevel['num'],
                    'award'    => $matchedLevel['award'],
                    'nickname' => $matchedLevel['nickname']
                ];
            } else {
                // 默认最低级别
                $item['favorability_level'] = $levelMapping[1] ?? [];
            }

            // 处理头像URL
            if (isset($item['avatar_url'])) {
                $item['avatar_url'] = cdnurl($item['avatar_url']);
            }
        }

        // 获取总数
        $total = $this->where([
            'user_id' => $userId,
            'status'  => 1
        ])->count();

        return [
            'list'       => $collectionList,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => ceil($total / $limit)
        ];
    }

    /**
     * 检查用户是否已收藏该角色
     * @param int $userId 用户ID
     * @param int $roleId 角色ID
     * @return bool 是否已收藏
     */
    public function isCollected($userId, $roleId)
    {
        return $this->where([
            'user_id' => $userId,
            'role_id' => $roleId,
            'status'  => 1
        ])->find() ? true : false;
    }
}
