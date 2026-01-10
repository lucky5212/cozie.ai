<?php

namespace app\model;

use Exception;
use think\Model;

class UserSignLog extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $name = 'user_sign_log';

    // 定义时间戳字段名
    protected $createTime = 'sign_time';
    protected $updateTime = false;

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;

    // 定义字段类型
    protected $type = [
        'id'          => 'integer',
        'user_id'     => 'integer',
        'sign_date'   => 'string',
        'sign_day'    => 'integer',
        'reward'      => 'integer',
        'sign_time'   => 'integer',
    ];

    /**
     * 检查用户今天是否已经签到
     * @param int $userId 用户ID
     * @return bool 是否已经签到
     */
    public function isSignedToday($userId)
    {
        $today = date('Y-m-d');
        return $this->where([
            'user_id' => $userId,
            'sign_date' => $today
        ])->find() ? true : false;
    }


    /**
     * 获取用户最近一次签到记录
     * @param int $userId 用户ID
     * @return mixed 签到记录
     */
    public function getLastSignRecord($userId)
    {
        return $this->where('user_id', $userId)
            ->order('sign_time', 'desc')
            ->find();
    }

    /**
     * 更新用户签到状态
     * 如果用户昨天没有签到，将签到天数重置为0
     * @param int $userId 用户ID
     * @return bool 更新结果
     */
    public function updateSignStatus($userId)
    {
        try {
            // 获取用户信息
            $user = User::find($userId);
            if (!$user) {
                return false;
            }

            // 获取最近一次签到记录
            $lastSignRecord = $this->getLastSignRecord($userId);
            $yesterday = date('Y-m-d', strtotime('-1 day'));

            // 检查用户是否需要重置签到天数
            if (!$lastSignRecord || $lastSignRecord['sign_date'] != $yesterday) {
                // 昨天没有签到，重置签到天数为0
                $user->sign_num = 0;
                $user->save();
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
