<?php
// app/controller/Error.php
namespace app\controller;

use think\exception\ValidateException;
use think\facade\Log;
use think\Response;
use think\exception\HttpException;

class Error
{
    /**
     * 处理404错误
     * @return Response
     */
    public function index()
    {
        // 记录404错误日志
        Log::error('404错误：页面不存在', [
            'url' => request()->url(),
            'method' => request()->method(),
            'ip' => request()->ip(),
            'params' => request()->param()
        ]);

        // 返回404响应
        return json([
            'code' => 404,
            'msg' => '页面不存在',
            'data' => []
        ], 404);
    }

    /**
     * 处理控制器不存在的情况
     * @return Response
     */
    public function __call($name, $arguments)
    {
        // 记录错误日志
        Log::error('控制器方法不存在', [
            'controller' => get_class($this),
            'method' => $name,
            'url' => request()->url(),
            'method' => request()->method(),
            'ip' => request()->ip(),
            'params' => request()->param()
        ]);

        // 返回404响应
        return json([
            'code' => 404,
            'msg' => '接口不存在',
            'data' => []
        ], 404);
    }
}
