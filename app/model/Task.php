<?php

namespace app\model;

use think\Model;

/**
 * 任务定义模型
 */
class Task extends Model
{
    protected $name = 'task';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    // 任务类型常量
    const TYPE_DAILY = 1;      // 每日任务
    const TYPE_ONCE = 2;       // 一次性任务

    // 任务状态常量
    const STATUS_ENABLED = 1;  // 启用
    const STATUS_DISABLED = 0; // 禁用

    /**
     * 获取所有启用的任务
     * @return array
     */
    public function getEnabledTasks()
    {
        return $this->where('status', self::STATUS_ENABLED)
            ->select()
            ->toArray();
    }
}
