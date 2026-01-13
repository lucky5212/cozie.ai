<?php

namespace app\model;

use think\Db;
use think\Model;
use think\facade\Console;
use app\common\JwtAuth;
use think\db\Query;
use think\db\Where;

class Role extends Model
{
    // // 设置字段信息
    // protected $schema = [
    //     'id'          => 'int',
    //     'title'        => 'string',
    //     'status'      => 'int',
    //     'create_time' => 'int',
    // ];
    // 设置当前模型对应的完整数据表名称
    protected $name = 'role';

    // // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'prevtime';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;


    public function userProfile()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function getRoleList($limit = 10, $page = 1, $category_id = 1, $keyword = '')
    {

        if ($keyword) {
            $query = $this
                ->with(['userProfile' => function (Query $query) {
                    $query->field('id,nickname,avatar');
                }])
                ->field('id,name,desc,occupation,avatar_url,category_id,user_id,chat_num,create_time')
                ->where('name', 'like', '%' . strtoupper($keyword) . '%') //->page($page, $limit)->select();
                ->where("status", 1);
        } else {
            $query = $this
                ->with(['userProfile' => function (Query $query) {
                    $query->field('id,nickname,avatar');
                }])
                ->field('id,name,desc,occupation,avatar_url,category_id,user_id,chat_num,create_time')
                ->where("status", 1)
                ->where('category_id', $category_id);
        }
        switch ($category_id) {
            case 75:
                $list = $query->page($page, $limit)->order('chat_num', 'desc')->select();
                $roleCount = $query->count();
                break;
            case 76:
                $list = $query->page(1, 50)->order('chat_num', 'desc')->select();
                $roleCount = 50;
                break;
            case 77:
                $list = $query->page($page, $limit)->order('create_time', 'desc')->select();
                $roleCount = $query->count();
                break;
            default:
                $roleCount = $query->count();
                $list = $query->page($page, $limit)->where('category_id', $category_id)->select();
                break;
        }
        foreach ($list as $key => $value) {
            $list[$key]['avatar_url'] = cdnurl($value['avatar_url']);
        }

        return [
            'total_count' => $roleCount, // 总记录数
            'total_pages' => ceil($roleCount / $limit), // 最后一页的页码
            'data' => $list, // 当前页的数据
        ];
    }

    public function userRoleList($userId, $limit = 10, $page = 1, $status = 1)
    {

        switch ($status) {
            case 1:
                // 查询用户创建的角色列表，关联RoleUserChat获取最大聊天数
                $query = $this->alias('r')
                    ->where(['r.user_id' => $userId])
                    ->where('r.status', 'in', [0, 1, 2])
                    ->join('role_user_chat ruc', 'r.id = ruc.role_id AND ruc.user_id = r.user_id', 'left')
                    ->field([
                        'r.id',
                        'r.name',
                        'r.avatar_url',
                        'r.status',
                        'r.age',
                        'r.occupation',
                        'r.chat_num',
                        'r.user_num',
                        'ruc.continuous_days'
                    ])
                    ->order('r.create_time', 'desc');
                break;
            case 2:
                $roleDetail = new RoleDraft();
                $query = $roleDetail->where(['user_id' => $userId])->field('id,name,avatar_url,status,age,occupation,chat_num,user_num')->order('create_time', 'desc');
                break;
            case 3:
                $where = ['r.status' => 3];
                $query = $this->alias('r')
                    ->where(['r.user_id' => $userId])
                    ->where($where)
                    ->field([
                        'r.id',
                        'r.name',
                        'r.avatar_url',
                        'r.age',
                        'r.status',
                        'r.occupation',
                        'r.chat_num',
                        'r.user_num',
                        'ruc.continuous_days'
                    ])
                    ->order('r.create_time', 'desc');
                break;
        }


        // 获取总记录数
        $roleCount = $query->count();
        // 获取当前页数据
        $list = $query->page($page, $limit)->select();
        $favorabilityLevels = RoleFavorability::order('num', 'asc')->select();
        $levelMapping = [];
        foreach ($favorabilityLevels as $level) {
            $levelMapping[$level['id']] = $level;
        }

        // 处理每个收藏记录的好感度级别
        foreach ($list as &$item) {
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



        // 处理头像URL
        foreach ($list as &$item) {
            $item['avatar_url'] = cdnurl($item['avatar_url']);
        }

        return [
            'total_count' => $roleCount, // 总记录数
            'total_pages' => ceil($roleCount / $limit), // 最后一页的页码
            'data' => $list, // 当前页的数据
        ];
    }
    public function getRoleInfo($roleId)
    {

        $role_info = $this
            ->with(['userProfile' => function (Query $query) {
                $query->field('id,nickname,avatar');
            }])
            ->field('id,name,desc,avatar_url,category_id,user_id,chat_num,user_num,create_time,greet_message,timbre_id,custom_tags,tags')
            ->where(['id' => $roleId])
            ->find();
        if ($role_info) {
            $role_info['avatar_url'] = cdnurl($role_info['avatar_url']);
        } else {
            return false;
        }

        // 获取标签 
        $tagString = RoleTag::where('id', 'in', $role_info['tags'])
            ->where('status', 1)
            ->value('GROUP_CONCAT(name SEPARATOR ",")');
        $role_info['tag_string'] = $tagString ?? '';
        $role_info['tags'] = $role_info['tags'] ?? '';
        return $role_info;
    }
}
