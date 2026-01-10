<?php
namespace app\utils;

use think\Exception;

class SimpleCrypto
{
    // 前后端约定的AppKey（32位）
    private static $appKey = '5f97e8a0b1c2d3e4f5a6b7c8d9e0f1a2';

    /**
     * AES-256-CBC解密
     * @param string $encryptedStr 加密后的Base64字符串
     * @param string $iv 16位IV向量（Base64编码）
     * @return string 解密后的业务数据JSON串
     */
    public static function aesDecrypt(string $encryptedStr, string $iv)
    {
        $key = self::$appKey;
        $iv = base64_decode($iv);
        // 验证IV长度（必须16位）
        if (strlen($iv) !== 16) {
            throw new Exception('IV向量必须为16位');
        }
        // AES解密
        $decrypted = openssl_decrypt(
            base64_decode($encryptedStr),
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        if (!$decrypted) {
            throw new Exception('AES解密失败');
        }
        return $decrypted;
    }

    /**
     * 验证HMAC-SHA256签名
     * @param string $data 解密后的业务数据
     * @param string $sign 前端传的签名
     * @param string $timestamp 时间戳
     * @return bool
     */
    public static function verifySign(string $data, string $sign, string $timestamp)
    {
        // 签名原文：业务数据JSON串 + 时间戳
        $signStr = $data . $timestamp;
        // 计算签名
        $calcSign = hash_hmac('sha256', $signStr, self::$appKey);
        // 防时序攻击比较
        return hash_equals($calcSign, $sign);
    }

    /**
     * 验证时间戳是否过期
     * @param string $timestamp
     * @return bool
     */
    public static function checkTimestamp(string $timestamp)
    {
       
        return abs(time() - $timestamp) <= 30000; // 5分钟有效期
    }



    /**
     * 生成16位随机IV向量（Base64编码）
     * @return string 16位IV的Base64编码字符串
     */
    public static function generateIv()
    {
        // 生成16位随机二进制数据
        $iv = openssl_random_pseudo_bytes(16);
        // 转Base64编码（方便传输）
        return base64_encode($iv);
    }

    /**
     * 生成秒级时间戳
     * @return string 当前时间戳（秒）
     */
    public static function generateTimestamp()
    {
        return (string)time();
    }

    /**
     * AES-256-CBC加密
     * @param string $data 原始业务数据（JSON字符串）
     * @param string $iv Base64编码的IV向量
     * @return string 加密后的Base64字符串
     * @throws \Exception
     */
    public static function aesEncrypt(string $data, string $iv)
    {
        $key = self::$appKey;
        // Base64解码IV为二进制
        $ivBin = base64_decode($iv);
        
        // 验证IV长度（必须16位）
        if (strlen($ivBin) !== 16) {
            throw new \Exception('IV向量必须为16位二进制数据');
        }

        // AES-256-CBC加密（OPENSSL_RAW_DATA模式返回二进制）
        $encrypted = openssl_encrypt(
            $data,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA, // 加密结果为二进制，需后续Base64编码
            $ivBin
        );

        if (!$encrypted) {
            throw new \Exception('AES加密失败');
        }

        // 加密结果转Base64编码（方便传输）
        return base64_encode($encrypted);
    }

    /**
     * 生成HMAC-SHA256签名
     * @param string $data 原始业务数据（JSON字符串）
     * @param string $timestamp 时间戳（秒）
     * @return string 十六进制小写签名
     */
    public static function generateSign(string $data, string $timestamp)
    {
        // 签名原文：业务数据 + 时间戳（与解密类的验签规则一致）
        $signStr = $data . $timestamp;
        // 计算HMAC-SHA256签名
        $sign = hash_hmac('sha256', $signStr, self::$appKey);
        return $sign;
    }

    /**
     * 一键生成加密所需的所有参数（快捷方法）
     * @param string $data 原始业务数据（JSON字符串）
     * @return array 包含iv、ts、data_encrypt、sign的参数数组
     * @throws \Exception
     */
    public static function encryptAll(string $data)
    {
        // 生成IV
        $iv = self::generateIv();
        // 生成时间戳
        $ts = self::generateTimestamp();
        // AES加密
        $dataEncrypt = self::aesEncrypt($data, $iv);
        // 生成签名
        $sign = self::generateSign($data, $ts);

        return [
            'iv' => $iv,
            'ts' => $ts,
            'data_encrypt' => $dataEncrypt,
            'sign' => $sign
        ];
    }

}