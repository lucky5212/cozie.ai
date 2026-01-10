<?php

namespace app\service;

use Exception;
use think\facade\Db;

// 任务完成判断示例代码
class TaskService
{
    /**
     * 检查任务是否完成
     * @param int $userId 用户ID
     * @param int $taskId 任务ID
     * @return bool 是否完成
     */
    public function checkTaskComplete($userId, $taskId)
    {
        $task = Db::name('task')->where('id', $taskId)->find();
        if (!$task || $task['status'] != 1) {
            return false;
        }

        $userTask = Db::name('user_task')->where(['user_id' => $userId, 'task_id' => $taskId])->find();

        // 根据任务类型检查完成条件
        switch ($task['id']) {
            case 1: // 创建角色
                $count = Db::name('role')->where('user_id', $userId)->whereTime('create_time', 'today')->count();
                return $count >= $task['target_value'];
            case 2: // 与角色聊天超过10句
                $count = Db::name('role_chat_history')->where('user_id', $userId)->whereTime('create_time', 'today')->count();
                return $count >= $task['target_value'];
            case 3: // 购买钻石
                $count = Db::name('money_log')->where(['user_id' => $userId, 'type' => 1])->whereTime('create_time', 'today')->count();
                return $count >= $task['target_value'];
                // 其他任务类型...
        }

        return false;
    }


    /**
     * 领取任务奖励
     * @param int $userId 用户ID
     * @param int $taskId 任务ID
     * @return bool 是否成功
     */
    public function getReward($userId, $taskId)
    {
        Db::startTrans();
        try {
            // 检查任务是否存在且启用
            $task = Db::name('task')->where(['id' => $taskId, 'status' => 1])->find();
            if (!$task) {
                throw new Exception('任务不存在或已禁用');
            }

            // 检查用户任务状态
            $userTask = Db::name('user_task')->where(['user_id' => $userId, 'task_id' => $taskId])->find();
            if (!$userTask) {
                // 创建用户任务记录
                Db::name('user_task')->insert([
                    'user_id' => $userId,
                    'task_id' => $taskId,
                    'current_value' => 0,
                    'is_completed' => 0,
                    'is_rewarded' => 0,
                    'create_time' => time(),
                    'update_time' => time()
                ]);
                throw new Exception('任务未完成');
            }

            // 检查任务是否完成
            if (!$userTask['is_completed']) {
                $isCompleted = $this->checkTaskComplete($userId, $taskId);
                if (!$isCompleted) {
                    throw new Exception('任务未完成');
                }
                // 更新任务完成状态
                Db::name('user_task')->where(['id' => $userTask['id']])->update([
                    'is_completed' => 1,
                    'last_complete_time' => time(),
                    'update_time' => time()
                ]);
            }

            // 检查是否已领取奖励
            if ($userTask['is_rewarded']) {
                // 检查是否是每日任务
                if ($task['type'] == 1) {
                    // 检查是否超过24小时
                    if (time() - $userTask['last_complete_time'] < 86400) {
                        throw new Exception('今日已领取奖励');
                    }
                } else {
                    throw new Exception('奖励已领取');
                }
            }

            // 发放奖励
            Db::name('user')->where('id', $userId)->inc('money', $task['reward_diamond'])->update();

            // 记录奖励日志
            Db::name('task_reward_log')->insert([
                'user_id' => $userId,
                'task_id' => $taskId,
                'reward_diamond' => $task['reward_diamond'],
                'reward_time' => time()
            ]);

            // 更新任务领取状态
            $updateData = [
                'is_rewarded' => 1,
                'update_time' => time()
            ];
            // 如果是每日任务，重置完成状态
            if ($task['type'] == 1) {
                $updateData['is_completed'] = 0;
                $updateData['current_value'] = 0;
            }
            Db::name('user_task')->where(['id' => $userTask['id']])->update($updateData);

            Db::commit();
            return ['success' => true, 'message' => '奖励领取成功', 'reward_diamond' => $task['reward_diamond']];
        } catch (Exception $e) {
            Db::rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
