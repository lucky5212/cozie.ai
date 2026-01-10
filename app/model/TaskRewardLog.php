<?php

namespace app\model;

use think\Model;

/**
 * 任务奖励日志模型
 */
class TaskRewardLog extends Model
{
    protected $name = 'task_reward_log';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
    
    /**
     * 记录奖励领取日志
     * @param int $userId 用户ID
     * @param int $taskId 任务ID
     * @param int $reward 奖励钻石数
     * @return bool
     */
    public function addRewardLog($userId, $taskId, $reward)
    {
        return $this->save([
            'user_id' => $userId,
            'task_id' => $taskId,
            'reward' => $reward,
            'create_time' => time()
        ]) > 0;
    }
}