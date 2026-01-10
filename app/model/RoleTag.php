<?php

namespace app\model;

use think\Db;
use think\Model;
use think\facade\Console;
use app\common\JwtAuth;
use think\db\Where;

class RoleTag extends Model
{
    // // 设置字段信息
    // protected $schema = [
    //     'id'          => 'int',
    //     'title'        => 'string',
    //     'status'      => 'int',
    //     'create_time' => 'int',
    // ];
    // 设置当前模型对应的完整数据表名称
    protected $name = 'role_tag';

    // // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'prevtime';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;



    public function tagList($type = null)
    {
        if ($type!=5) {
            return $this->where('status', 1)->where('id','not in',[75,76,77])->where('type',$type)->field('id,name,type')->select();
        }
        return $this->where('status', 1)->where('id','not in',[75,76,77])->field('id,name,type')->select();
    }

}