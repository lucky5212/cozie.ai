<?php

namespace app\model;

use think\Db;
use think\Model;
use think\facade\Console;
use app\common\JwtAuth;
use think\db\Where;

class MoneyLog extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $name = 'user_money_log';

    // // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'prevtime';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;


    /**
     * 添加金额日志
     * @param int $userId 用户ID
     * @param float $money 金额
     * @param string $memo 备注
     * @return bool 是否成功
     */
    public function addMoneyLog($userId, $money, $before, $after, $memo = '')
    {

        MoneyLog::create([
            'user_id' => $userId,
            'money'   => $money,
            'before'  => $before,
            'after'   => $after,
            'memo'    => $memo
        ]);
    }

    /**
     * 获取用户钻石明细
     * @param int $userId 用户ID
     * @return array 金额日志数组
     */
    public function getMoneyLogs($userId, $page = 1, $limit = 10, $status = 1)
    {
        $where = [];
        if ($status == 1) {
            $where[] = ['memo', 'in', ['签到奖励', '新人奖励', '任务奖励']];
        } elseif ($status == 2) {
            $where[] = ['memo', 'in', ['充值']];
        } elseif ($status == 3) {
            $where[] = ['memo', 'in', ['消耗']];
        }
        $moneyLogs = $this->where('user_id', $userId)->order('id', 'desc')->page($page, $limit)
            ->where($where)
            ->field('id, money, memo, createtime')
            ->select()
            ->toArray();
        $total = $this->where('user_id', $userId)->count();
        $totalPage = ceil($total / $limit);
        return [
            'total_count' => $total,
            'total_pages' => $totalPage,
            'data' => $moneyLogs
        ];
    }
}
