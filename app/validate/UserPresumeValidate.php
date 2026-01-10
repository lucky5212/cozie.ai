<?php
// app/validate/UserPresumeValidate.php
namespace app\validate;

use think\Validate;

class UserPresumeValidate extends Validate      
{
    // 验证规则
    protected $rule = [
        'role_id' => 'require|number',
        'name' => 'require|max:40',
        'work' => 'max:20',
        'desc' => 'max:200',
        'favourite' => 'max:20',
        'loathe' => 'max:20',
        'other' => 'max:1000',
    ];
    
    // 错误信息
    protected $message = [
        'role_id.require' => '角色ID不能为空',
        'role_id.number' => '角色ID必须是数字',
        'name.require' => '名称不能为空',
        'name.max' => '名称不能超过40个字符',
        'work.max' => '职业或身份不能超过20个字符',
        'desc.max' => '简介不能超过200个字符',
        'favourite.max' => '最喜欢的不能超过20个字符',
        'loathe.max' => '最讨厌的不能超过20个字符',
        'other.max' => '其他信息不能超过1000个字符',
    ];
    
    // 场景定义
    protected $scene = [
        'add' => ['role_id', 'name', 'work', 'desc', 'favourite', 'loathe', 'other'],
        'edit' => ['name', 'work', 'desc', 'favourite', 'loathe', 'other'],
    ];
    
}