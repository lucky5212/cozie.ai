<?php
namespace app\middleware;

use app\common\JwtAuth;
use think\Request;
use Closure;

class JwtAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) || !empty($_SERVER['HTTP_VIA'])){
        //     return json(['code'=>403,'msg'=>'']);//禁止代理
        // }
		$request_uri = $_SERVER['REQUEST_URI'];//获取url
        $pathinfos = explode('?',$request_uri)[0];//以url的?来分割url并且转化成数组
       	$pathinfo = strtolower($pathinfos);//全部小写
       	$white_list = [
       		'/usetoken/index',
			'/usetoken/auth'
       	]; // 白名单，不用token验证
       	// if(!in_array($pathinfo, $white_list)){
            // 用户请求Token
       		$token = $request->header('access-token');
       		// 验证是否存在token
       		if (empty($token)) {
       			return json(['code'=>401,'msg'=>'token不能为空']);
       		} else {
       			$result = JwtAuth::checkToken($token);  //调用jwtauth.php下的验证函数    				
       			if($result['code']!=200){ // token正确时，执行接口的php
       			    return json($result);
       			}
       		}
       	// }
        return $next($request);
			   
    }
}

