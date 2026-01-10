<?php

namespace app\controller\v1;

use think\response\Json;
use app\BaseController;
use app\common\JwtAuth;
use app\model\MoneyLog;
use app\model\SystemMessage;
use app\model\UserTag;
use app\model\User;
use app\model\UserPresume;
use app\model\UserSign;
use app\model\UserSignLog;
use Exception;

class Login extends BaseController
{

    public function index()
    {
        return 'hello,Login!';
    }

    /**
     * 游客登录接口
     * 通过 device_id 创建或获取游客账号，返回 token 供 iOS 应用后续调用
     * 后期可绑定 Apple ID
     */
    public function guestLogin(): Json
    {
        $rs = [
            'code' => 0,
            'msg'  => '',
            'data' => [],
        ];
        try {
            // 获取原始 POST 内容（iOS 端 JSON 提交）
            $data = request()->data;

            // 检查解密后的数据是否存在
            if (!isset($data) || !is_array($data)) {
                return json(['code' => 400, 'msg' => '无效的请求数据']);
            }

            // 参数校验
            if (empty($data['device_id'])) {
                return json(['code' => 400, 'msg' => '缺少 device_id']);
            }

            $deviceId = trim($data['device_id']);
            // 业务模型
            $guestModel = new User();
            // 根据 device_id 查找已有游客
            $user = $guestModel->where('device_id', $deviceId)->find();
            $inviteCode = generateRandomCode(6, 'user', 'invitation_code');
            // 不存在则创建
            if (!$user) {
                $uid = $guestModel->insertGetId([
                    'device_id' => $deviceId,
                    'createtime' => time(),
                    'prevtime' => time(),
                    'gender' => '女',
                    'level' => 0,
                    'invitation_code' => $inviteCode,
                    'money' => 100,
                    'status' => 'normal',
                    'nickname' => 'User' . generateRandomCode(4),
                ]);
                // 插入系统消息
                $systemMessageModel = new SystemMessage();

                // 插入金额日志
                $moneyLogModel = new MoneyLog();
                $moneyLogModel->addMoneyLog($uid, 100, 0, 100, '新人奖励');

                $systemMessageModel->insertSystemMessage([
                    'user_id' => $uid,
                    'type' => '系统消息',
                    'event_user_id' => 0,
                    'content' => '新用戶通知：新人福利到！免费贈送 100 钻石，快去和心怡角色開啟故事吧~',
                    'create_time' => time(),
                ]);
            } else {
                $uid = $user['id'];
                $ip = request()->ip();
                $time = time();
                if ($user->status != 'normal') {
                    $rs['code'] = 400;
                    $rs['msg']  = 'Account is locked';
                    return json($rs);
                }
                //判断连续登录和最大连续登录
                if ($user->logintime < \fast\Date::unixtime('day')) {
                    $user->successions = $user->logintime < \fast\Date::unixtime('day', -1) ? 1 : $user->successions + 1;
                    $user->maxsuccessions = max($user->successions, $user->maxsuccessions);
                }
                $user->prevtime = $user->logintime;
                //记录本次登录的IP和时间
                $user->loginip = $ip;
                $user->logintime = $time;
                //重置登录失败次数
                $user->loginfailure = 0;
                $user->save();
            }
            $_token = \fast\Random::uuid();
            // 生成JWT token
            $tokenData = array(
                'token' => $_token,
                'uid'  => $uid,
            );
            $token = JwtAuth::signToken($tokenData);
            $userinfo = $guestModel->getUserinfo($uid);

            $userinfo['avatar'] = $$guestModel->getUserinfo($uid);
            return json([
                'code' => 0,
                'msg'  => '登录成功',
                'data' => [
                    'uid'      => $uid,
                    'token'    => $token,
                    'is_guest' => true,
                    'userinfo' => $userinfo,
                ]
            ]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => '登录失败：' . $e->getMessage()]);
        }
    }

    // 获取个人信息
    public function getUserinfo(): Json
    {
        $rs = ['code' => 0, 'msg' => '', 'data' => []];

        $result = JwtAuth::decodeToken($this->request->header('access-token'));

        // 更新签到状态
        $userSignModel = new UserSignLog();
        $userSignModel->updateSignStatus($result['uid']);

        // 获取用户信息
        $userModel = new User();
        $userinfo = $userModel->getUserinfo($result['uid']);
        if (!$userinfo) {
            $rs['code'] = 400;
            $rs['msg']  = 'User not found';
            return json($rs);
        }

        // 检查今日是否已签到
        $signStatus = $userSignModel->isSignedToday($result['uid']) ? 1 : 0;
        $userinfo['sign_status'] = $signStatus;

        $rs['data']['userinfo'] = $userinfo;
        return json($rs);
    }
}
