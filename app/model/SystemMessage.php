<?php
// app/model/AiUserTag.php
namespace app\model;

use think\Model;

class SystemMessage extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $name = 'user_system_message';

    // 自动写入时间戳
    protected $autoWriteTimestamp = false;




    public function insertSystemMessage($data)
    {

        $this->save($data);
    }

    // 获取用户消息列表
    public function messageList($userId, $limit = 10, $offset = 0)
    {

        $result =  $this->where(['user_id' => $userId])
            ->order('create_time', 'desc')
            ->field('id,user_id,type,content,create_time')
            ->page($offset, $limit)
            ->select()
            ->toArray();

        foreach ($result as $key => $value) {
            $result[$key]['create_time'] = date('Y-m-d H:i:s', $value['create_time']);
        }
        $count = $this->where(['user_id' => $userId])->count();
        return [
            'total_count' => $count, // 总记录数
            'total_pages' => ceil($count / $limit), // 最后一页的页码
            'data' => $result, // 当前页的数据
        ];
    }
}
