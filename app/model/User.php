<?php

namespace app\model;

use think\Db;
use think\Model;
use think\facade\Console;
use app\common\JwtAuth;
use think\db\Where;

class User extends Model
{
    // // 设置字段信息
    // protected $schema = [
    //     'id'          => 'int',
    //     'title'        => 'string',
    //     'status'      => 'int',
    //     'create_time' => 'int',
    // ];
    // 设置当前模型对应的完整数据表名称
    protected $name = 'user';

    // // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'prevtime';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;


    public static function getUserinfo($uid)
    {
        return self::where('id', $uid)->field('id,avatar,level,gender,successions,maxsuccessions,nickname,invitation_code,desc,work,tags_ids,tags_list,sign_num,lang')->find();
    }
}
