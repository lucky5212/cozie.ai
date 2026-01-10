<?php
// app/model/AiUserTag.php
namespace app\model;

use think\Model;

class UserTag extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $name = 'user_tag';

    // 自动写入时间戳
    protected $autoWriteTimestamp = false;
}