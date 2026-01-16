<?php
// app/job/DailyMemorySummaryJob.php
namespace app\job;

use app\controller\v1\ChatController;
use app\service\OpenRouterService;
use think\facade\Db;
use think\facade\Log;
use think\queue\Job;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class DailyMemorySummaryJob
{
    /**
     * 执行每日记忆总结队列任务
     * @param Job $job 队列任务对象
     * @param array $data 任务数据（包含user_id和role_id）
     * @return void
     */
    public function fire(Job $job, array $data)
    {
        try {
            $userId = $data['user_id'];
            $roleId = $data['role_id'];
            $lang = $data['lang'] ?? 'zh-Hant';

            Log::info('开始执行每日记忆总结队列任务', [
                'user_id' => $userId,
                'role_id' => $roleId,
                'job_id' => $job->getJobId()
            ]);
            // 调用每日记忆总结方法
            $result = $this->generateDailyMemorySummary($userId, $roleId, $lang);

            if ($result) {
                Log::info('每日记忆总结队列任务执行成功', [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'job_id' => $job->getJobId()
                ]);
            } else {
                Log::info('每日记忆总结队列任务无需执行', [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'job_id' => $job->getJobId(),
                    'reason' => '已存在总结或无记忆数据'
                ]);
            }

            // 任务完成，删除队列任务
            $job->delete();
        } catch (\Exception $e) {
            Log::error('每日记忆总结队列任务执行失败', [
                'user_id' => $userId,
                'role_id' => $roleId,
                'job_id' => $job->getJobId(),
                'error_message' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            // 检查重试次数
            if ($job->attempts() > 3) {
                // 重试次数超过3次，删除任务
                $job->delete();
                Log::error('每日记忆总结队列任务重试次数超过限制，已删除', [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'job_id' => $job->getJobId()
                ]);
            } else {
                // 重新放入队列，5分钟后重试
                $job->release(300);
            }
        }
    }



    /**
     * 生成昨日记忆总结和日记（每日一次）
     * @param int $userId 用户ID
     * @param int $roleId 角色ID
     * @return bool
     */
    private function generateDailyMemorySummary($userId, $roleId, $lang)
    {
        // 检查今日是否已经生成过昨日记忆总结和日记

        $hasSummary = Db::name('role_memory')
            ->where(['user_id' => $userId, 'role_id' => $roleId])
            ->where('category', 'medium_memory')
            ->where('sub_category', 'daily_summary')
            ->whereDay('create_time')
            ->count() > 0;

        $hasDiary = Db::name('role_memory')
            ->where(['user_id' => $userId, 'role_id' => $roleId])
            ->where('category', 'medium_memory')
            ->where('sub_category', 'daily_diary')
            ->whereDay('create_time')
            ->count() > 0;

        if ($hasSummary && $hasDiary) {
            return false; // 今日已生成过
        }
        // 获取昨日的所有记忆
        $yesterdayMemories = Db::name('role_memory')
            ->where(['user_id' => $userId, 'role_id' => $roleId])
            ->whereDay('create_time', 'yesterday')
            ->where('status', 1)
            ->select()
            ->toArray();

        if (empty($yesterdayMemories)) {
            return false; // 昨日没有记忆
        }


        if ($lang == 'zh-Hant') {
            // 构建昨日记忆内容
            $memoriesContent = "昨日记忆内容：\n";
            foreach ($yesterdayMemories as $memory) {
                $category = $memory['category'] == 'user_memory' ? '【用户资料】' : '【重要事件】';
                $subCategory = $memory['sub_category'];
                $content = $memory['content'];
                $memoriesContent .= "{$category}({$subCategory})：{$content}\n";
            }
        } else {
            // 构建昨日记忆内容
            $memoriesContent = "Yesterday's Memories:\n";
            foreach ($yesterdayMemories as $memory) {
                $category = $memory['category'] == 'user_memory' ? '【User Profile】' : '【Important Event】';
                $subCategory = $memory['sub_category'];
                $content = $memory['content'];
                $memoriesContent .= "{$category}({$subCategory})：{$content}\n";
            }
        }

        // 构建昨日记忆简单内容（用于日记生成）
        $simpleMemories = [];
        foreach ($yesterdayMemories as $memory) {
            $simpleMemories[] = $memory['content'];
        }
        $simpleMemoriesContent = implode(', ', $simpleMemories);


        // 生成记忆总结
        $summaryResult = false;
        if (!$hasSummary) {
            try {
                // 构建每日记忆总结提示词
                $dailySummaryPrompt = $this->buildDailyMemorySummaryPrompt($memoriesContent, date('Y-m-d', strtotime('-1 day')), $lang);
                // 调用OpenAI API生成总结
                $summaryResponse = OpenRouterService::chat([$dailySummaryPrompt]);

                // 记录原始响应日志
                \think\facade\Log::info('每日记忆总结API原始响应', [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'response' => $summaryResponse
                ]);

                // 解析总结数据
                $summaryData = json_decode($summaryResponse, true);

                // 检查JSON解析结果
                if (json_last_error() !== JSON_ERROR_NONE) {
                    \think\facade\Log::error('每日记忆总结JSON解析失败', [
                        'user_id' => $userId,
                        'role_id' => $roleId,
                        'json_error' => json_last_error_msg(),
                        'raw_response' => $summaryResponse
                    ]);
                } else {
                    // 保存总结为新的记忆
                    if (isset($summaryData['memories']) && is_array($summaryData['memories'])) {
                        $roleMemory = new \app\model\RoleMemory();
                        $roleMemory->saveMemories($userId, $roleId, $summaryData['memories']);
                        $summaryResult = true;
                    } else {
                        \think\facade\Log::error('每日记忆总结格式不符合预期', [
                            'user_id' => $userId,
                            'role_id' => $roleId,
                            'summary_data' => $summaryData
                        ]);
                    }
                }
            } catch (\Exception $e) {
                // 记录详细错误日志，但不中断主流程
                \think\facade\Log::error('生成每日记忆总结失败', [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'yesterday' => date('Y-m-d', strtotime('-1 day')),
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'stack_trace' => $e->getTraceAsString()
                ]);
            }
        }
        // 生成日记
        $diaryResult = false;
        if (!$hasDiary) {
            $diaryResult = $this->generateDailyDiary($userId, $roleId, $simpleMemoriesContent, date('Y-m-d', strtotime('-1 day')));
        }



        return $summaryResult || $diaryResult;
    }


    /**
     * 构建每日记忆总结提示词
     * @param string $memoriesContent 昨日记忆内容
     * @param string $date 日期
     * @return array
     */
    private function buildDailyMemorySummaryPrompt(string $memoriesContent, string $date, string $lang): array
    {
        // 获取模板
        // 获取模板
        if ($lang == "zh-Hant") {
            $template = DB::name('config')->where(['id' => 21])->value('value');
        } else {
            $template = DB::name('config')->where(['id' => 22])->value('value');
        }
        // 初始化 Twig
        $loader = new ArrayLoader([
            'summaryPrompt' => $template,
        ]);
        $twig = new Environment($loader);

        $variables = [
            'data' => $date,
            'memoriesContent' => $memoriesContent,
        ];
        $prompt = $twig->render('summaryPrompt', $variables);
        // 每日记忆总结提示词
        $prompt = [
            "role" => "system",
            "content" => $prompt,
        ];
        //         $prompt = [
        //             "role" => "system",
        //             "content" => "## 目標
        // 請分析以下昨日（{$date}）的所有記憶內容，總結成一段精簡的回顧。\n\n## 具體要求
        // 1. **總結範圍**：全面覆蓋昨日所有記憶的核心信息
        // 2. **字數限制**：嚴格控制在200字以內
        // 3. **內容要求**：
        //    - 保留最重要的用戶資料更新
        //    - 保留最重要的事件發展和情感變化
        //    - 忽略重複或不重要的細節
        //    - 使用客觀、簡潔的語言
        // 4. **格式要求**：
        //    - 以第一人称視角總結（模擬角色的回憶）
        //    - 突出當日的重要事件和關鍵信息
        //    - 保持時間邏輯順序
        // \n## 輸出格式
        // - ***僅***可輸出JSON格式，嚴禁任何解釋、註釋、說明文字
        // - **必須直接以 `{` 起始，`}` 結尾**
        // - 只允許以下格式：
        // \```json
        // {
        //  \"memories\": [
        //     {
        //       \"content\": \"[總結內容]\",
        //       \"category\": \"medium_memory\",
        //       \"sub_category\": \"daily_summary\"
        //     }
        //   ]
        // }
        // \```
        // \n## 昨日記憶內容
        // {$memoriesContent}"
        //         ];

        return $prompt;
    }



    /**
     * 生成每日日记
     * @param int $userId 用户ID
     * @param int $roleId 角色ID
     * @param string $memoriesContent 昨日记忆内容
     * @param string $date 日期
     * @return bool
     */
    private function generateDailyDiary($userId, $roleId, $memoriesContent, $date)
    {
        try {
            // 构建日记生成提示词
            $diaryPrompt = $this->buildDailyDiaryPrompt($memoriesContent, $userId, $roleId);

            // 调用OpenAI API生成日记
            $diaryResponse = OpenRouterService::chat([$diaryPrompt]);

            // 记录原始响应日志
            \think\facade\Log::info('每日日记生成API原始响应', [
                'user_id' => $userId,
                'role_id' => $roleId,
                'response' => $diaryResponse
            ]);

            // 保存日记为新的记忆
            $diaryData = [
                [
                    'content' => $diaryResponse,
                    'category' => 'medium_memory',
                    'sub_category' => 'daily_diary'
                ]
            ];

            $roleMemory = new \app\model\RoleMemory();
            $result = $roleMemory->saveMemories($userId, $roleId, $diaryData);

            return $result;
        } catch (\Exception $e) {
            // 记录详细错误日志
            \think\facade\Log::error('生成每日日记失败', [
                'user_id' => $userId,
                'role_id' => $roleId,
                'date' => $date,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * 构建每日日记提示词
     * @param string $memoriesContent 昨日记忆内容
     * @param int $userId 用户ID
     * @param int $roleId 角色ID
     * @return array
     */
    private function buildDailyDiaryPrompt($memoriesContent, $userId, $roleId, $lang = "zh-Hant"): array
    {
        // 获取角色信息
        $roleData = Db::name('role')->where(['id' => $roleId])->find();

        // 获取模板
        if ($lang == "zh-Hant") {
            $template = DB::name('config')->where(['id' => 20])->value('value');
        } else {
            $template = DB::name('config')->where(['id' => 19])->value('value');
        }
        // 初始化 Twig
        $loader = new ArrayLoader([
            'dailyDiaryPrompt' => $template,
        ]);
        $twig = new Environment($loader);


        // 获取用户信息
        $userPresume = Db::name('user_presume')->where(['uid' => $userId, 'role_id' => $roleId])->find();
        if (!$userPresume) {
            $userData = Db::name('user')->where(['id' => $userId])->find();
            $userNickname = $userData['nickname'] ?? '用户';
            $userGender = $userData['gender'] ?? 'MAN';
        } else {
            $userNickname = $userPresume['name'] ?? '用户';
            $userGender = $userPresume['gender'] ?? 'MAN';
        }

        $roleNickname = $roleData['name'] ?? '角色';
        $roleGender = $roleData['gender'] ?? 'WOMAN';

        $variables = [
            'memoriesContent' => $memoriesContent,
            'userNickname' => $userNickname,
            'userGender' => $userGender,
            'roleNickname' => $roleNickname,
            'roleGender' => $roleGender,
            'lang' => $lang,
        ];
        $prompt = $twig->render('dailyDiaryPrompt', $variables);

        //         $prompt = [
        //             "role" => "system",
        //             "content" => "Your task is to summarize and polish the memory information between `user` and `assistant` in the form of a diary: 
        //  <memory> 
        //  {$memoriesContent} 
        //  </memory> 

        //  #Character background information 
        //  `user` nickname: `{$userNickname}` and gender: '{$userGender}' 
        //  `assistant` nickname: `{$roleNickname}` and gender: '{$roleGender}' 

        //  Output rules: 
        //  1. Generate a diary entry as {$roleNickname}. 
        //  2. The content should be sweet and have a human touch. 
        //  3. Do not generate titles or dates. 
        //  4. The output language is `Traditional Chinese`. "
        //         ];

        $prompt = [
            "role" => "system",
            "content" => $prompt,
        ];
        return $prompt;
    }
}
