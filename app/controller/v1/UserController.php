<?php

namespace app\controller\v1;

use think\response\Json;
use think\facade\Cache;
use app\BaseController;
use app\model\User;
use app\model\UserTag;
use app\model\UserSign;
use Exception;
use think\facade\Db;
use app\common\JwtAuth;
use app\model\MoneyLog;
use app\model\UserIncantation;
use app\model\UserSignLog;
use app\model\UserFollow;
use app\model\Task;


class UserController extends BaseController
{
    /** 
     * 获取AI用户标签列表 
     * @return Json 
     */
    public function getUserTagList(): Json
    {
        try {
            $cacheKey = 'user_tag_list';
            if (Cache::has($cacheKey)) {
                $tags = Cache::get($cacheKey);
            } else {
                // 查询启用状态的标签列表 
                $tags = UserTag::where('status', '1')
                    ->order('id', 'asc')
                    ->field('id, name')
                    ->select()
                    ->toArray();
                Cache::set($cacheKey, $tags, 1800);
            }

            return json([
                'code' => 0,
                'msg' => '获取标签列表成功',
                'data' => $tags
            ]);
        } catch (Exception $e) {
            return json([
                'code' => 500,
                'msg' => '获取标签列表失败：' . $e->getMessage()
            ]);
        }
    }


    public function editInfo(): Json
    {
        // 验证用户身份
        $token = JwtAuth::decodeToken($this->request->header('Access-Token'));
        if (!$token) {
            return json([
                'code' => 401,
                'msg' => '未授权'
            ]);
        }
        $userId = $token['uid'];
        try {
            $param = $this->request->param();
            // 验证参数
            $validate = $this->validate($param, [
                'name' => 'require|max:20',
                'gender' => 'require|in:男,女,未知|max:3',
                'desc' => 'max:600',
            ]);
            if ($validate !== true) {
                return json([
                    'code' => 400,
                    'msg' => $validate
                ]);
            }

            $data = [
                'nickname' => $param['name'],
                'gender' => $param['gender'],
                'desc' => $param['desc'],
                'avatar' => $param['avatar'],
                'work' => $param['work'],
                'custom_tags' => $param['custom_tags'],
                'tags_ids' => $param['tags']
            ];
            if (isset($param['custom_tags'])) {
                $tags = Db::name('user_tag')->where('id', 'in', $param['tags'])->column('name');
                $data['tags_list'] = implode(',', $tags);
            }
            $user = User::find($userId);
            if (!$user) {
                return json([
                    'code' => 404,
                    'msg' => '用户不存在'
                ]);
            }
            $user->save($data);
            return json([
                'code' => 0,
                'msg' => '用户信息更新成功'
            ]);
        } catch (Exception $e) {
            return json([
                'code' => 500,
                'msg' => '用户信息更新失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 关注/取消关注角色
     * @return Json
     */
    public function toggleFollow(): Json
    {
        // 验证用户身份
        $token = JwtAuth::decodeToken($this->request->header('Access-Token'));
        if (!$token) {
            return json([
                'code' => 401,
                'msg' => '未授权'
            ]);
        }
        $userId = $token['uid'];

        // 获取关注的角色ID
        $followUserId = (int)$this->request->post('follow_user_id');
        if (empty($followUserId)) {
            return json([
                'code' => 400,
                'msg' => '关注的角色ID不能为空'
            ]);
        }

        if ($userId == $followUserId) {
            return json([
                'code' => 400,
                'msg' => '不能关注自己'
            ]);
        }
        try {
            // 调用模型方法切换关注状态
            $followModel = new UserFollow();
            $result = $followModel->toggleFollow($userId, $followUserId);

            if ($result['success']) {
                return json([
                    'code' => 200,
                    'msg' => $result['message'],
                    'data' => [
                        'status' => $result['status']
                    ]
                ]);
            } else {
                return json([
                    'code' => 500,
                    'msg' => '操作失败'
                ]);
            }
        } catch (Exception $e) {
            return json([
                'code' => 500,
                'msg' => '操作失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 获取用户关注列表
     * @return Json
     */
    public function getFollowList(): Json
    {
        // 验证用户身份
        $token = JwtAuth::decodeToken($this->request->header('Access-Token'));
        if (!$token) {
            return json([
                'code' => 401,
                'msg' => '未授权'
            ]);
        }
        $userId = $token['uid'];

        // 获取分页参数
        $page = max(1, (int)$this->request->get('page', 1));
        $limit = max(1, min(50, (int)$this->request->get('limit', 20)));

        try {
            // 调用模型方法获取关注列表
            $followModel = new UserFollow();
            $result = $followModel->getFollowList($userId, $page, $limit);
            return json([
                'code' => 200,
                'msg' => '请求成功',
                'data' => [
                    'current_page' => (int)$page,  // 当前页码
                    'page_size' => (int)$limit,     // 每页记录数
                    'total_count' => $result['total_count'], // 总记录数
                    'total_pages' => $result['total_pages'], // 最后一页的页码
                    'data' => $result['data'], // 当前页的数据
                ]
            ]);
        } catch (Exception $e) {
            return json([
                'code' => 500,
                'msg' => '获取关注列表失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 获取角色粉丝列表
     * @return Json
     */
    public function getFansList(): Json
    {
        // 验证用户身份
        $token = JwtAuth::decodeToken($this->request->header('Access-Token'));
        if (!$token) {
            return json([
                'code' => 401,
                'msg' => '未授权'
            ]);
        }
        $userId = $token['uid'];

        // 获取分页参数
        $page = max(1, (int)$this->request->get('page', 1));
        $limit = max(1, min(50, (int)$this->request->get('limit', 20)));

        try {
            // 调用模型方法获取粉丝列表
            $followModel = new UserFollow();
            $result = $followModel->getFansList($userId, $page, $limit);
            return json([
                'code' => 200,
                'msg' => '请求成功',
                'data' => [
                    'current_page' => (int)$page,  // 当前页码
                    'page_size' => (int)$limit,     // 每页记录数
                    'total_count' => $result['total_count'], // 总记录数
                    'total_pages' => $result['total_pages'], // 最后一页的页码
                    'data' => $result['data'], // 当前页的数据
                ]
            ]);
        } catch (Exception $e) {
            return json([
                'code' => 500,
                'msg' => '获取粉丝列表失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 用户签到接口
     * @return Json
     */
    public function signIn(): Json
    {
        // 验证用户身份
        $token = JwtAuth::decodeToken($this->request->header('Access-Token'));
        if (!$token) {
            return json([
                'code' => 401,
                'msg' => '未授权'
            ]);
        }
        $userId = $token['uid'];
        $moneyLogModel = new MoneyLog();

        try {
            // 检查用户是否已经签到
            $userSignModel = new UserSignLog();
            if ($userSignModel->isSignedToday($userId)) {
                return json([
                    'code' => 400,
                    'msg' => '今日已签到'
                ]);
            }

            // 获取用户信息
            $user = User::find($userId);
            if (!$user) {
                return json([
                    'code' => 404,
                    'msg' => '用户不存在'
                ]);
            }

            // 计算连续签到天数
            $lastSignRecord = $userSignModel->getLastSignRecord($userId);
            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $signDay = 1;

            if ($lastSignRecord) {
                if ($lastSignRecord['sign_date'] == $yesterday) {
                    // 昨天签过到，连续天数+1
                    $signDay = $lastSignRecord['sign_day'] + 1;
                }
                // 如果超过7天，则最多算7天
                if ($signDay > 7) {
                    $signDay += 1;
                }
            }

            // 获取奖励
            $signReward = Db::name('user_sign')
                ->where('id', $signDay)
                ->cache('user_sign_' . $userId, 600)
                ->value('diamond_num');

            // 开启事务
            Db::startTrans();

            // 记录签到
            $userSignModel->save([
                'user_id' => $userId,
                'sign_date' => $today,
                'sign_day' => $signDay,
                'reward' => $signReward
            ]);

            // 记录金额日志
            $moneyLogModel->addMoneyLog($userId, $signReward, $user->money, $user->money + $signReward, '签到奖励');

            // 更新用户信息
            $user->money += $signReward;
            $user->sign_num = $signDay;
            $user->save();

            // 提交事务
            Db::commit();

            return json([
                'code' => 200,
                'msg' => '签到成功',
                'data' => [
                    'reward' => $signReward,
                    'total_money' => $user->money,
                    'sign_num' => $signDay
                ]
            ]);
        } catch (Exception $e) {
            // 回滚事务
            Db::rollback();
            return json([
                'code' => 500,
                'msg' => '签到失败：' . $e->getMessage()
            ]);
        }
    }

    // 钻石明细
    public function diamondDetails(): Json
    {
        // 验证用户身份
        $token = JwtAuth::decodeToken($this->request->header('Access-Token'));
        if (!$token) {
            return json([
                'code' => 401,
                'msg' => '未授权'
            ]);
        }
        $page = $this->request->param('page', 1);
        $limit = $this->request->param('limit', 10);
        $status = $this->request->param('status', 1);
        if (empty($page)) {
            $page = 1;
        }
        if (empty($limit)) {
            $limit = 10;
        }
        if (empty($status)) {
            $status = 1;
        }
        $userId = $token['uid'];
        $moneyLogModel = new MoneyLog();
        $result = $moneyLogModel->getMoneyLogs($userId, $page, $limit, $status);
        return json([
            'code' => 200,
            'msg' => '请求成功',
            'data' => [
                'current_page' => (int)$page,  // 当前页码
                'page_size' => (int)$limit,     // 每页记录数
                'total_count' => $result['total_count'], // 总记录数
                'total_pages' => $result['total_pages'], // 最后一页的页码
                'data' => $result['data'], // 当前页的数据
            ]
        ]);
    }



    /**
     * 获取用户任务列表
     * @return Json
     */
    public function getTaskList(): Json
    {
        // 验证用户身份
        $token = JwtAuth::decodeToken($this->request->header('Access-Token'));
        if (!$token) {
            return json(['code' => 401, 'msg' => '未授权']);
        }
        $userId = $token['uid'];

        try {
            // 获取所有启用的任务
            $tasks = Db::name('task')->where('status', 1)->select()->toArray();

            // 获取用户任务状态
            $userTasks = Db::name('user_task')->where('user_id', $userId)->column('*', 'task_id');

            // 组装任务列表
            $result = [];
            foreach ($tasks as $task) {
                $userTask = $userTasks[$task['id']] ?? [];

                // 检查任务是否完成
                $isCompleted = $userTask['is_completed'] ?? false;
                if (!$isCompleted) {
                    $isCompleted = $this->checkTaskComplete($userId, $task['id']);
                }

                $result[] = [
                    'task_id' => $task['id'],
                    'name' => $task['name'],
                    'description' => $task['description'],
                    'type' => $task['type'],
                    'target_value' => $task['target_value'],
                    'reward_diamond' => $task['reward_diamond'],
                    'is_completed' => $isCompleted,
                    'is_rewarded' => $userTask['is_rewarded'] ?? false,
                    'current_value' => $userTask['current_value'] ?? 0
                ];
            }
            return json([
                'code' => 200,
                'msg' => '请求成功',
                'data' => $result
            ]);
        } catch (Exception $e) {
            return json([
                'code' => 500,
                'msg' => '获取任务列表失败：' . $e->getMessage()
            ]);
        }
    }


    /**
     * 检查任务完成状态
     * @param int $userId 用户ID
     * @param int $taskId 任务ID
     * @return bool
     */
    public function checkTaskComplete($userId, $taskId)
    {
        // 检查任务是否存在
        $task = Db::name('task')->where('id', $taskId)->find();
        if (!$task || $task['status'] != 1) {
            return false;
        }

        // 如果是每日任务，先检查是否需要重置
        if ($task['type'] == 1) { // 1=每日任务
            $this->resetDailyTasks($userId);
        }

        // 查询用户任务状态
        $userTask = Db::name('user_task')->where(['user_id' => $userId, 'task_id' => $taskId])->find();

        // 根据任务类型检查完成条件
        $currentValue = 0;
        switch ($task['id']) {
            case 1: // 创建角色
                $currentValue = Db::name('role')->where('user_id', $userId)->whereTime('create_time', 'today')->count();
                break;
            case 2: // 与角色聊天超过10句
                $currentValue = Db::name('role_chat_history')->where('user_id', $userId)->whereTime('create_time', 'today')->count();
                break;
            case 3: // 购买钻石
                // $currentValue = Db::name('money_log')->where('user_id', $userId)->where('type', '购买钻石')->whereTime('create_time', 'today')->count();
                break;
                // 其他任务类型...
        }

        // 判断任务是否完成
        $isCompleted = $currentValue >= $task['target_value'];

        // 更新用户任务状态
        $this->updateUserTaskStatus($userId, $taskId, $currentValue, $isCompleted);

        return $isCompleted;
    }

    /**
     * 领取任务奖励
     * @return Json
     */
    public function claimTaskReward(): Json
    {
        // 验证用户身份
        $token = JwtAuth::decodeToken($this->request->header('Access-Token'));
        if (!$token) {
            return json([
                'code' => 401,
                'msg' => '未授权'
            ]);
        }
        $userId = $token['uid'];

        // 获取任务ID参数
        $taskId = (int)$this->request->post('task_id');
        if (empty($taskId)) {
            return json([
                'code' => 400,
                'msg' => '任务ID不能为空'
            ]);
        }

        try {
            // 检查任务是否存在
            $task = Db::name('task')->where('id', $taskId)->where('status', 1)->find();
            if (!$task) {
                return json([
                    'code' => 404,
                    'msg' => '任务不存在或已禁用'
                ]);
            }

            // 检查用户任务状态
            $userTask = Db::name('user_task')->where([
                'user_id' => $userId,
                'task_id' => $taskId
            ])->find();

            // 检查任务是否已完成且未领取奖励
            if (!$userTask || $userTask['is_completed'] != 1 || $userTask['is_rewarded'] != 0) {
                return json([
                    'code' => 400,
                    'msg' => '任务未完成或已领取奖励'
                ]);
            }

            // 开启事务
            Db::startTrans();

            // 获取用户信息
            $user = User::find($userId);
            if (!$user) {
                Db::rollback();
                return json([
                    'code' => 404,
                    'msg' => '用户不存在'
                ]);
            }

            // 更新用户钻石数量
            $rewardAmount = $task['reward_diamond'];
            $user->money += $rewardAmount;
            $user->save();

            // 更新任务状态为已领取奖励
            Db::name('user_task')->where('id', $userTask['id'])->update([
                'is_rewarded' => 1,
                'update_time' => time()
            ]);

            // 记录奖励日志
            $moneyLogModel = new MoneyLog();
            $moneyLogModel->addMoneyLog(
                $userId,
                $rewardAmount,
                $user->money - $rewardAmount,
                $user->money,
                '任务奖励'
            );

            // 提交事务
            Db::commit();

            return json([
                'code' => 200,
                'msg' => '奖励领取成功',
                'data' => [
                    'reward_amount' => $rewardAmount,
                    'total_money' => $user->money
                ]
            ]);
        } catch (Exception $e) {
            // 回滚事务
            Db::rollback();
            return json([
                'code' => 500,
                'msg' => '领取奖励失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 更新用户任务状态
     * @param int $userId 用户ID
     * @param int $taskId 任务ID
     * @param int $currentValue 当前值
     * @param bool $isCompleted 是否完成
     */
    private function updateUserTaskStatus($userId, $taskId, $currentValue, $isCompleted)
    {
        // 检查用户任务记录是否存在
        $userTask = Db::name('user_task')->where(['user_id' => $userId, 'task_id' => $taskId])->find();

        if ($userTask) {
            // 更新现有记录
            Db::name('user_task')->where('id', $userTask['id'])->update([
                'current_value' => $currentValue,
                'is_completed' => $isCompleted ? 1 : 0,
                'update_time' => time()
            ]);
        } else {
            // 创建新记录
            Db::name('user_task')->insert([
                'user_id' => $userId,
                'task_id' => $taskId,
                'current_value' => $currentValue,
                'is_completed' => $isCompleted ? 1 : 0,
                'is_rewarded' => 0,
                'create_time' => time(),
                'update_time' => time()
            ]);
        }
    }

    /**
     * 重置用户的每日任务
     * @param int $userId 用户ID
     */
    private function resetDailyTasks($userId)
    {
        // 检查是否需要重置（每天重置一次）
        $cacheKey = 'task_reset_' . $userId;
        $lastResetDate = Cache::get($cacheKey);
        $today = date('Y-m-d');

        if ($lastResetDate != $today) {
            // 获取每日任务列表
            $dailyTasks = Db::name('task')->where('type', 1)->where('status', 1)->column('id');
            if (!empty($dailyTasks)) {
                // 重置用户的每日任务状态
                Db::name('user_task')->where('user_id', $userId)
                    ->where('task_id', 'in', $dailyTasks)
                    ->update([
                        'current_value' => 0,
                        'is_completed' => 0,
                        'is_rewarded' => 0,
                        'update_time' => time()
                    ]);
            }
            // 更新缓存
            Cache::set($cacheKey, $today, 86400);
        }
    }


    // 兑换邀请奖励
    public function invitationReward()
    {

        // 验证用户身份
        $token = JwtAuth::decodeToken($this->request->header('Access-Token'));
        if (!$token) {
            return json(['code' => 401, 'msg' => '未授权']);
        }
        $userId = $token['uid'];
        $code = $this->request->param('code');
        if (empty($code)) {
            return json(['code' => 400, 'msg' => '邀请码不能为空']);
        }
        $oldUserInfo = Db::name('user')->where('invitation_code', $code)->find();
        $newUserInfo = Db::name('user')->where('id', $userId)->find();
        if (!$oldUserInfo) {
            return json(['code' => 400, 'msg' => '邀请码错误']);
        } else if ($oldUserInfo['id'] == $userId) {
            return json(['code' => 400, 'msg' => '不不能邀请自己']);
        } else if ($newUserInfo['use_code_status'] == 1) {
            return json(['code' => 400, 'msg' => '只能使用一次']);
        }
        $data = [
            'user_id' => $userId,
            'task_id' => 4,
            'current_value' => 1,
            'is_completed' => 1,
            'is_rewarded' => 1,
            'create_time' => time(),
            'update_time' => time(),
        ];
        Db::startTrans();
        try {
            Db::name('user_task')->insert($data);
            $moneyLogModel = new MoneyLog();
            // 获取奖励数量
            $awardNum = Task::where('id', 4)->value('reward_diamond');
            // 老人奖励
            $moneyLogModel->addMoneyLog($oldUserInfo['id'], $awardNum, $oldUserInfo['money'], $oldUserInfo['money'] + $awardNum, '邀新奖励');
            // 新人奖励
            $moneyLogModel->addMoneyLog($newUserInfo['id'], $awardNum, $newUserInfo['money'], $newUserInfo['money'] + $awardNum, '邀新奖励');
            Db::name('user')->where('id', $oldUserInfo['id'])->update(['money' => $oldUserInfo['money'] + $awardNum]);

            // 更新用户邀请码状态
            Db::name('user')->where('id', $userId)->update(['user_code' => $code, 'use_code_status' => 1, 'money' => $newUserInfo['money'] + $awardNum]);
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 500, 'msg' => '数据库操作失败：' . $e->getMessage()]);
        }

        return json(['code' => 200, 'msg' => '兑换成功']);
    }

    /**
     * 添加用户咒语
     * @return Json
     */
    public function saveIncantation(): Json
    {
        // 验证用户身份
        $token = JwtAuth::decodeToken($this->request->header('Access-Token'));
        if (!$token) {
            return json([
                'code' => 401,
                'msg' => '未授权'
            ]);
        }
        $userId = $token['uid'];

        // 获取请求参数
        $param = $this->request->post();

        // 验证参数
        $validate = $this->validate($param, [
            'title|标题' => 'require|max:20',
            'content|内容' => 'max:120',
        ]);
        if ($validate !== true) {
            return json([
                'code' => 400,
                'msg' => $validate
            ]);
        }
        $title = $param['title'];
        $content = $param['content'] ?? '';

        $count = Db::name('user_incantation')->where('user_id', $userId)->count();
        if ($count >= 20) {
            return json([
                'code' => 400,
                'msg' => '用户已创建过20个咒语，无法创建更多'
            ]);
        }
        try {
            // 准备数据，type默认为2（用户）
            $data = [
                'user_id' => $userId,
                'title' => $title,
                'content' => $content,
                'type' => 2,
                'create_time' => time()
            ];
            // 插入数据
            $id = Db::name('user_incantation')->insertGetId($data);
            return json([
                'code' => 200,
                'msg' => '添加成功',
                'data' => [
                    'id' => $id
                ]
            ]);
        } catch (Exception $e) {
            return json([
                'code' => 500,
                'msg' => '添加失败：' . $e->getMessage()
            ]);
        }
    }

    /**
     * 获取用户咒语列表
     * @return Json
     */
    public function getIncantationList(): Json
    {
        // 验证用户身份
        $token = JwtAuth::decodeToken($this->request->header('Access-Token'));
        if (!$token) {
            return json([
                'code' => 401,
                'msg' => '未授权'
            ]);
        }
        $userId = $token['uid'];

        // 获取分页参数
        $page = max(1, (int)$this->request->get('page', 1));
        $limit = max(1, min(50, (int)$this->request->get('limit', 20)));

        try {
            // 查询用户咒语列表，type=2（用户）
            $query = Db::name('user_incantation')
                ->where(['user_id' => $userId, 'type' => 2])
                ->order('create_time', 'desc');

            // 获取总记录数
            $totalCount = $query->count();
            // 获取当前页数据
            $list = $query->page($page, $limit)->field('id,title,content,create_time')->select()->toArray();
            // 处理头像URL
            foreach ($list as  $key => $item) {
                $list[$key]['create_time'] = date('Y-m-d H:i:s', $item['create_time']);
            }
            return json([
                'code' => 200,
                'msg' => '请求成功',
                'data' => [
                    'current_page' => (int)$page,
                    'page_size' => (int)$limit,
                    'total_count' => $totalCount,
                    'total_pages' => ceil($totalCount / $limit),
                    'data' => $list
                ]
            ]);
        } catch (Exception $e) {
            return json([
                'code' => 500,
                'msg' => '获取列表失败：' . $e->getMessage()
            ]);
        }
    }
}
