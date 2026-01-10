<?php
// app/model/AiUserPresume.php
namespace app\model;

use think\Model;

class UserPresume extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $name = 'user_presume';

    // 定义时间戳字段名

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;
}
