<?php

namespace app\model;

use think\Db;
use think\Model;
use think\facade\Console;
use app\common\JwtAuth;
use think\db\Where;

class RoleChatHistory extends Model
{
    // // 设置字段信息
    // protected $schema = [
    //     'id'          => 'int',
    //     'title'        => 'string',
    //     'status'      => 'int',
    //     'create_time' => 'int',
    // ];
    // 设置当前模型对应的完整数据表名称
    protected $name = 'role_chat_history';

    // // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'prevtime';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;

    // 定义时间戳字段的获取器，自动转换为可读格式
    // public function getCreateTimeAttr($value)
    // {
    //     return date('Y-m-d H:i:s', $value);
    // }

    // public function getUpdateTimeAttr($value)
    // {
    //     return date('Y-m-d H:i:s', $value);
    // }



    /**
     * 保存用户和角色的聊天记录
     * @param int $userId 用户ID
     * @param int $roleId 角色ID
     * @param string $userMessage 用户消息
     * @param string $assistantMessage 助手消息
     * @param int $score 分数
     * @param string $score_reason 分数原因
     * @param string $message_id 消息ID
     * @return string 聊天记录ID
     */
    public function saveChatHistory($userId, $roleId, $userMessage, $assistantMessage, $score, $score_reason, $message_id)
    {
        // if ($score != 0) {
        //     User::where('id', 1)->inc('favorability', $score)->update();
        // }
        $roleUserChat = new RoleUserChat();
        $chatHistory = $roleUserChat->where('role_id', $roleId)->where('user_id', $userId)->find();
        $role = new Role();
        if (!$chatHistory) {
            $roleUserChat->save([
                'user_id' => $userId,
                'role_id' => $roleId,
                'continuous_days' => 1,
                'favorability' => $score,
                'last_chat_date' => time(),
            ]);
            $role->where('id', $roleId)->inc('chat_num', 1)->inc('user_num', 1)->update();
        } else {
            $continuousDays = $chatHistory['continuous_days'];
            $favorability = $chatHistory['favorability'];
            $lastChatDate = $chatHistory['last_chat_date'];

            // 计算今天和昨天的时间范围
            $todayStart = strtotime('today'); // 今天00:00:00
            $yesterdayStart = strtotime('yesterday'); // 昨天00:00:00
            $yesterdayEnd = $todayStart - 1; // 昨天23:59:59

            if ($lastChatDate >= $todayStart) {
                // 今天内，不改变连续天数，只更新时间
            } elseif ($lastChatDate >= $yesterdayStart && $lastChatDate <= $yesterdayEnd) {
                // 昨天内，连续天数+1
                $continuousDays++;
            } else {
                // 既不是今天也不是昨天，重置为1
                $continuousDays = 1;
            }
            $favorability += $score;
            $roleUserChat->where('id', $chatHistory['id'])->update([
                'continuous_days' => $continuousDays,
                'favorability' => $favorability,
                'last_chat_date' => time(),
            ]);
            $role->where('id', $roleId)->inc('chat_num', 1)->update();
        }


        $data = [
            'user_id' => $userId,
            'role_id' => $roleId,
            'question' => $userMessage,
            'answer' => $assistantMessage,
            'score' => $score,
            'score_reason' => $score_reason,
            'create_time' => time(),
            'update_time' => time(),
            'is_read' => 0,
        ];
        if (!$message_id) {
            $this->save($data);
        } else {
            $data['is_regenerate'] = 1;
            $this->where('id', $message_id)->update($data);
            $this->id = $message_id;
        }
        return $this->id;
    }

    public function getChatHistory($userId, $roleId, $limit, $page)
    {
        // 确保$page和$limit是整数类型，避免字符串-整数运算错误
        $page = (int)$page;
        $limit = (int)$limit;

        $offset = ($page - 1) * $limit;
        $chatHistory = $this->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->order('id', 'desc')
            ->where('status', 1)
            // ->where('is_read', 0) 
            ->field('id,user_id,role_id,question,answer,FROM_UNIXTIME(create_time, "%Y-%m-%d %H:%i:%s") as create_time,FROM_UNIXTIME(update_time, "%Y-%m-%d %H:%i:%s") as update_time,is_read')
            ->limit($offset, $limit)
            ->select();

        // 获取总记录数并计算总页数
        $totalCount = $this->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->count();

        $totalPages = ceil($totalCount / $limit);
        return ['chat_history' => $chatHistory, 'total_count' => $totalCount, 'total_pages' => $totalPages];
    }

    // 删除角色聊天记录
    public function delChatHistory($userId, $roleId)
    {
        // 删除聊天记录
        $this->where('user_id', $userId)->where('role_id', $roleId)->update(['status' => 0]);
        // 删除记忆 日记
        $roleMemory = new RoleMemory();
        $roleMemory->where('user_id', $userId)->where('role_id', $roleId)->update(['status' => 0]);

        // 删除心声
        $roleHeart = new InnerThought();
        $roleHeart->where('user_id', $userId)->where('role_id', $roleId)->update(['status' => 0]);

        // 删除最近聊天列表
        $roleUserChat = new RoleUserChat();
        $roleUserChat->where('user_id', $userId)->where('role_id', $roleId)->delete();
    }
}
