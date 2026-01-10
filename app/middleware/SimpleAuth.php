<?php 
namespace app\middleware;

use app\utils\SimpleCrypto;
use think\Response;

class SimpleAuth
{
    public function handle($request, \Closure $next): Response
    {
        try {
            // 1. 获取前端传的3个参数
            $dataEncrypt = $request->post('data_encrypt');
            $sign = $request->post('sign');
            $ts = $request->post('ts');
            $iv = $request->post('iv');

            // 2. 验证参数是否完整
            if (empty($dataEncrypt) || empty($sign) || empty($ts) || empty($iv)) {
                return json(['code' => 400, 'msg' => '参数缺失']);
            }

            // 3. 验证时间戳
            if (!SimpleCrypto::checkTimestamp($ts)) {
                return json(['code' => 403, 'msg' => '请求过期']);
            }

            // 4. AES解密业务数据
            $data = SimpleCrypto::aesDecrypt($dataEncrypt, $iv);

            // 5. 验证签名
            if (!SimpleCrypto::verifySign($data, $sign, $ts)) {
                return json(['code' => 403, 'msg' => '签名错误']);
            }

            // 6. 解密数据传给控制器
            $request->data = json_decode($data, true);

            return $next($request);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()]);
        }
    }
}