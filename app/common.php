<?php
// 应用公共文件

use think\facade\Db;
use think\facade\Lang;

/**
 * 操作成功返回的数据
 * @param string $msg    提示信息
 * @param mixed  $data   要返回的数据
 * @param int    $code   错误码，默认为1
 * @param string $type   输出类型
 * @param array  $header 发送的 Header 信息
 */
function success($msg = '', $data = null, $code = 1, $type = null, array $header = [])
{
    result($msg, $data, $code, $type, $header);
}

/**
 * 操作失败返回的数据
 * @param string $msg    提示信息
 * @param mixed  $data   要返回的数据
 * @param int    $code   错误码，默认为0
 * @param string $type   输出类型
 * @param array  $header 发送的 Header 信息
 */
function error($msg = '', $data = null, $code = 401, $type = null, array $header = [])
{
    return json([
        'code' => $code,
        'msg' => $msg
    ]);
}

/**
 * 返回封装后的 API 数据到客户端
 * @access protected
 * @param mixed  $msg    提示信息
 * @param mixed  $data   要返回的数据
 * @param int    $code   错误码，默认为0
 * @param string $type   输出类型，支持json/xml/jsonp
 * @param array  $header 发送的 Header 信息
 * @return void
 * @throws HttpResponseException
 */
function result($msg, $data = null, $code = 0, $type = null, array $header = [])
{
    $result = [
        'code' => $code,
        'msg'  => $msg,
        'time' => $_SERVER['REQUEST_TIME'],
        'data' => $data,
    ];
    // 如果未设置类型则使用默认类型判断
    $type = $type ?: config('default_response_type');

    if (isset($header['statuscode'])) {
        $code = $header['statuscode'];
        unset($header['statuscode']);
    } else {
        //未设置状态码,根据code值判断
        $code = $code >= 1000 || $code < 200 ? 200 : $code;
    }
    $response = \think\Response::create($result, $type, $code)->header($header);
    throw new \think\exception\HttpResponseException($response);
}



/**
 * 邀请码字符池（排除易混淆字符：0/O、1/l、8/B、9/q 等）
 */

/**
 * 生成随机邀请码
 * @param int $length 邀请码长度（默认8位）
 * @param string $table 校验唯一性的表名（如user）
 * @param string $field 邀请码字段名（如invite_code）
 * @return string 唯一的邀请码
 */
function generateRandomCode(int $length = 8, string $table = '', string $field = ''): string
{
    $charPool =  '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';
    $poolLength = strlen($charPool);
    $code = '';

    // 循环生成，直到拿到唯一的邀请码
    do {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            // 随机取字符池中的字符
            $code .= $charPool[mt_rand(0, $poolLength - 1)];
        }
        // 若指定了表和字段，校验是否已存在
        $isExist = $table && $field ? Db::name($table)->where($field, $code)->find() : false;
    } while ($isExist);
    return $code;
}


function cdnurl($url, $domain = false)
{
    $regex = "/^((?:[a-z]+:)?\/\/|data:image\/)(.*)/i";
    $cdnurl = env('upload.cdnurl');
    $url = preg_match($regex, $url) || ($cdnurl && stripos($url, $cdnurl) === 0) ? $url : $cdnurl . $url;
    if ($domain && !preg_match($regex, $url)) {
        $url = $domain . $url;
    }
    return $url;
}

/**
 * 获取语言内容
 * @param string $key 语言键名，格式：分组.键名，如：common.success
 * @param string $lang 语言标识，如：zh-cn, en
 * @param string $default 默认值，当找不到对应语言项时返回
 * @return string 语言内容
 */
function lang_content($key, $lang = null, $default = '')
{
    // 如果没有指定语言，使用当前请求的语言或默认语言
    if (is_null($lang)) {
        // 使用 Lang 门面的 range 方法获取当前语言
        $lang = Lang::range() ?: config('lang.default_lang');
    }

    // 确保语言包文件已加载
    $langFile = root_path() . 'lang' . DIRECTORY_SEPARATOR . $lang . '.php';
    if (is_file($langFile)) {
        // 加载语言包文件
        $langContent = include $langFile;

        // 解析键名，支持多级分组
        $keys = explode('.', $key);
        $value = $langContent;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                // 找不到对应语言项，返回默认值
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    // 语言包文件不存在，返回默认值
    return $default;
}

/**
 * 设置当前语言
 * @param string $lang 语言标识，如：zh-cn, en
 * @return bool 是否设置成功
 */
function set_lang($lang)
{
    // 检查语言是否在允许列表中
    $allowList = config('lang.allow_lang_list');
    if (!in_array($lang, $allowList)) {
        return false;
    }

    // 设置当前语言
    Lang::setRange($lang);



    // 如果启用了Cookie记录，更新Cookie
    if (config('lang.use_cookie')) {
        cookie(config('lang.cookie_var'), $lang, 3600 * 24 * 30);
    }

    return true;
}

/**
 * 获取当前语言
 * @return string 当前语言标识
 */
function get_lang()
{
    return Lang::range() ?: config('lang.default_lang');
}



/**
 * 语言包获取方法
 * @param string $name 语言变量名
 * @param array $vars 变量替换
 * @param string $lang 语言代码
 * @return string
 */
function lang($name, $vars = [], $lang = '')
{
    return Lang::get($name, $vars, $lang);
}
