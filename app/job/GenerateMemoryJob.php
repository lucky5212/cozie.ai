<?php
// app/job/GenerateMemoryJob.php
namespace app\job;

use think\facade\Log;
use think\queue\Job;

class GenerateMemoryJob
{
    /**
     * 执行生成记忆总结的任务
     * @param Job $job 队列任务对象
     * @param array $data 任务数据
     * @return void
     */
    public function fire(Job $job, array $data)
    {
        try {
            // 从数据中获取必要参数
            $userId = $data['user_id'];
            $roleId = $data['role_id'];

            Log::info('开始执行记忆总结异步任务', [
                'user_id' => $userId,
                'role_id' => $roleId
            ]);

            // 获取聊天记录用于总结
            $chatHistory = \think\facade\Db::name('role_chat_history')
                ->where(['user_id' => $userId, 'role_id' => $roleId])
                ->order('id', 'desc')
                ->limit(5) // 获取最近5条消息用于总结
                ->select()
                ->reverse();

            $memoryPrompt = \app\controller\v1\ChatController::buildMemorySummaryPrompt($chatHistory);
            // 调用OpenAI API生成记忆
            $memoriesResponse = \app\service\OpenRouterService::chat([$memoryPrompt]);
            // 解析记忆数据
            $memories = json_decode($memoriesResponse, true);
            // 保存记忆数据
            if (isset($memories['memories']) && is_array($memories['memories'])) {
                $roleMemory = new \app\model\RoleMemory();
                $roleMemory->saveMemories($userId, $roleId, $memories['memories']);
            }

            Log::info('记忆总结异步任务执行成功', [
                'user_id' => $userId,
                'role_id' => $roleId
            ]);

            // 完成任务
            $job->delete();
        } catch (\Exception $e) {
            // 记录错误日志
            Log::error('记忆总结异步任务执行失败', [
                'user_id' => isset($data['user_id']) ? $data['user_id'] : null,
                'role_id' => isset($data['role_id']) ? $data['role_id'] : null,
                'error_message' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            // 如果任务失败了，检查失败次数
            if ($job->attempts() > 3) {
                Log::error('记忆总结异步任务多次执行失败，已删除', [
                    'user_id' => isset($data['user_id']) ? $data['user_id'] : null,
                    'role_id' => isset($data['role_id']) ? $data['role_id'] : null
                ]);
                $job->delete();
            } else {
                // 重新执行任务
                $job->release(60); // 1分钟后重试
            }
        }
    }
}
