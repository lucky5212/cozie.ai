<?php
// app/validate/RoleValidate.php
namespace app\validate;

use think\Validate;

class RoleValidate extends Validate
{
    // 验证规则
    protected $rule = [
        'avatar_url' => 'require',
        'timbre_id' => 'require',
        'tags' => 'require',
        'name' => 'require|max:20',
        'gender' => 'require|in:男,女,未知',
        'age' => 'require|number|between:18,999',
        'occupation' => 'require|max:50',
        'desc' => 'require|max:3000',
        'character' => 'max:3000',
        'greet_message' => 'require|max:600',
    ];
    
    // 错误信息
    protected $message = [
        'avatar_url.require' => '角色头像不能为空',
        'timbre_id.require' => '请选择音色',
        'desc.require' => '请输入角色简介',
        'tags.require' => '请选择角色标签',
        'name.max' => '角色昵称不能超过20个字符',
        'name.require' => '请输入角色昵称',
        'gender.require' => '请选择性别',
        'gender.in' => '请选择正确的性别（男、女、其他）',
        'age.require' => '角色年龄不能为空',
        'age.number' => '角色年龄必须是数字',
        'age.between' => '角色年龄必须在18~999之间',
        'occupation.max' => '角色身份不能超过50个字符',
        'occupation.require' => '请输入角色身份',
        'desc.max' => '角色简介不能超过3000个字符',
        'character.max' => '智能体设定不能超过3000个字符',
        'greet_message.require' => '请输入角色的第一句话',
        'greet_message.max' => '角色的第一句话不能超过600个字符',
    ];
    
    // 场景定义
    protected $scene = [
        'create' => ['avatar_url', 'name', 'gender', 'tags','age', 'occupation', 'desc', 'character', 'greet_message', 'timbre_id'],
    ];
    
}