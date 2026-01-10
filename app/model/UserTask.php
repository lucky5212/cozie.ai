<?php

namespace app\model;

use think\Model;

/**
 * 用户任务状态模型
 */
class UserTask extends Model
{
    protected $name = 'user_task';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;
    
    // 任务完成状态常量
    const STATUS_INCOMPLETE = 0;  // 未完成
    const STATUS_COMPLETED = 1;   // 已完成
    
    // 奖励领取状态常量
    const REWARDED_NO = 0;        // 未领取
    const REWARDED_YES = 1;       // 已领取
    
    /**
     * 获取用户的任务状态
     * @param int $userId 用户ID
     * @return array
     */
    public function getUserTasks($userId)
    {
        return $this->where('user_id', $userId)
            ->column('*', 'task_id');
    }
    
    /**
     * 获取用户特定任务的状态
     * @param int $userId 用户ID
     * @param int $taskId 任务ID
     * @return array|null
     */
    public function getUserTask($userId, $taskId)
    {
        return $this->where([
            'user_id' => $userId,
            'task_id' => $taskId
        ])->find();
    }
    
    /**
     * 更新用户任务进度
     * @param int $userId 用户ID
     * @param int $taskId 任务ID
     * @param int $currentValue 当前进度值
     * @param bool $isCompleted 是否完成
     * @return bool
     */
    public function updateTaskProgress($userId, $taskId, $currentValue, $isCompleted = false)
    {
        $data = [
            'current_value' => $currentValue,
            'is_completed' => $isCompleted ? self::STATUS_COMPLETED : self::STATUS_INCOMPLETE,
            'update_time' => time()
        ];
        
        $userTask = $this->getUserTask($userId, $taskId);
        if ($userTask) {
            return $this->where('id', $userTask['id'])->update($data) > 0;
        } else {
            $data['user_id'] = $userId;
            $data['task_id'] = $taskId;
            $data['create_time'] = time();
            $data['is_rewarded'] = self::REWARDED_NO;
            return $this->save($data) > 0;
        }
    }
    
    /**
     * 重置用户的每日任务
     * @param int $userId 用户ID
     * @return bool
     */
    public function resetDailyTasks($userId)
    {
        // 获取每日任务列表
        $taskModel = new Task();
        $dailyTasks = $taskModel->where('type', Task::TYPE_DAILY)
            ->where('status', Task::STATUS_ENABLED)
            ->column('id');
        
        if (empty($dailyTasks)) {
            return true;
        }
        
        // 重置用户的每日任务状态
        return $this->where('user_id', $userId)
            ->where('task_id', 'in', $dailyTasks)
            ->update([
                'current_value' => 0,
                'is_completed' => self::STATUS_INCOMPLETE,
                'is_rewarded' => self::REWARDED_NO,
                'update_time' => time()
            ]) > 0;
    }
    
    /**
     * 标记任务奖励已领取
     * @param int $userId 用户ID
     * @param int $taskId 任务ID
     * @return bool
     */
    public function markRewardReceived($userId, $taskId)
    {
        return $this->where([
            'user_id' => $userId,
            'task_id' => $taskId
        ])->update([
            'is_rewarded' => self::REWARDED_YES,
            'update_time' => time()
        ]) > 0;
    }
}