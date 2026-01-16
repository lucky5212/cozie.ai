<?php

namespace app\controller\v1;

use app\BaseController;
use think\response\Json;
use app\utils\SimpleCrypto;

class TestController extends BaseController
{
    public function decrypt(): Json
    {
        // 获取解密后的业务数据
        $data = request()->data;
        return json([
            'code' => 200,
            'msg' => '成功',
            'data' => $data
        ]);
    }
    public function encryption(): Json
    {
        // 加密

        try {
            // 1. 原始业务数据（模拟前端的业务参数）
            $originalData = json_encode([
                'device_id' => $this->request->param('device_id'),
                'lang' => $this->request->param('lang'),
            ], JSON_UNESCAPED_UNICODE);

            // 2. 一键生成所有加密参数
            $encryptParams = SimpleCrypto::encryptAll($originalData);

            // 3. 返回加密后的参数（可直接作为POST请求参数）
            return json([
                'iv' => $encryptParams['iv'],
                'ts' => $encryptParams['ts'],
                'data_encrypt' => $encryptParams['data_encrypt'],
                'sign' => $encryptParams['sign'],
            ]);
        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg' => '加密失败：' . $e->getMessage()
            ]);
        }
    }
}
