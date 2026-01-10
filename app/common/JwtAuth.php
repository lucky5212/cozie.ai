<?php
// jwt验证
namespace app\common;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;
// use Firebase\JWT\UnexpectedValueException;
class JwtAuth
{
    private static $key = '!@#$%*&';

    public static function signToken($data)
    {
        $payload = [
            'iss' => 'http://example.org',
            'aud' => '',
        	'nbf' => time()-2,//在此之前不可用
            'iat' => time(),//发布时间
        	'exp' => time()+3153600000,//过期时间
			'data' => $data, //自定义
        ];
	// self::$key调用上面的private static $key = 'example_key';
        return JWT::encode($payload, self::$key, 'HS256');
    }

    public static function checkToken($token)
    {
       try {
            $decoded = JWT::decode($token, new Key(self::$key, 'HS256'));
            return ['code' => 200,'msg' => 'token有效'];
        } catch (SignatureInvalidException $e) { //签名不正确
			 return ['code' => 204,'msg' => '签名不正确'];
		} catch (BeforeValidException $e) { // 签名在某个时间点之后才能用
			return ['code' => 203,'msg' => 'token未生效'];
		} catch (ExpiredException $e) { // token过期
			return ['code' => 202,'msg' => '登录超时，请重新登录'];

		} catch (\Throwable $e) { //其他错误
			// return ['code' => 500,'msg' => $e->getMessage()];
            return ['code' => 204,'msg' => '签名不正确'];
		}
    }
    /**
     * 解码token
     * @param $token
     * @return array|int[]
     */
    public static function decodeToken($token)
    {
        $key = self::$key; //自定义的一个随机字串用户于加密中常用的 盐  salt
        $res['status'] = false;
        try {
            JWT::$leeway = 60; //当前时间减去60，把时间留点余地
            $decoded = JWT::decode($token, new Key($key, 'HS256')); //HS256方式，这里要和签发的时候对应
            $arr = (array) $decoded;

            return (array) $arr['data'];
        
        } catch (SignatureInvalidException $e) { //签名不正确
            $res['info'] = "签名不正确";
            return $res;
        } catch (BeforeValidException $e) { // 签名在某个时间点之后才能用
            $res['info'] = "token失效";
            return $res;
        } catch (ExpiredException $e) { // token过期
            $res['info'] = "token失效";
            return $res;
        } catch (Exception $e) { //其他错误
            $res['info'] = "未知错误";
            return $res;
        }
    }
}

