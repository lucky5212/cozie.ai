# SimpleCrypto 加密工具使用文档

## 1. 概述

SimpleCrypto 是一个基于 PHP 的加密工具类，提供 AES-256-CBC 加密解密、HMAC-SHA256 签名验证等功能，用于保护前后端数据传输的安全性。

## 2. 基本信息

- **类路径**：`app\utils\SimpleCrypto`
- **依赖**：PHP OpenSSL 扩展
- **加密算法**：AES-256-CBC
- **签名算法**：HMAC-SHA256
- **密钥长度**：256位 (32字节)
- **IV向量长度**：16位 (16字节)

## 3. 一键生成加密参数

### 3.1 功能说明

`encryptAll` 方法是一个快捷方法，用于一键生成加密所需的所有参数，无需单独调用各个生成方法。

```php
/**
 * 一键生成加密所需的所有参数（快捷方法）
 * @param string $data 原始业务数据（JSON字符串）
 * @return array 包含iv、ts、data_encrypt、sign的参数数组
 * @throws \Exception
 */
public static function encryptAll(string $data)
```

### 3.2 返回参数说明

| 参数名 | 类型 | 说明 |
|--------|------|------|
| iv | string | 16位IV向量的Base64编码 |
| ts | string | 秒级时间戳 |
| data_encrypt | string | AES加密后的业务数据（Base64编码） |
| sign | string | HMAC-SHA256签名（十六进制小写） |

## 4. 加密步骤详解

### 4.1 详细流程

1. **准备原始业务数据**：将需要加密的业务数据转换为JSON字符串
2. **生成IV向量**：调用 `generateIv()` 生成16位随机IV向量（Base64编码）
3. **生成时间戳**：调用 `generateTimestamp()` 生成当前秒级时间戳
4. **AES加密**：使用AES-256-CBC算法加密业务数据
5. **生成签名**：使用HMAC-SHA256算法生成签名
6. **返回结果**：将所有参数打包返回

### 4.2 步骤分解

#### 步骤1：准备原始业务数据
```php
// 业务数据示例
$businessData = [
    'user_id' => 123,
    'username' => 'test_user',
    'action' => 'login'
];

// 转换为JSON字符串
$data = json_encode($businessData);
```

#### 步骤2：生成IV向量
```php
// 生成16位随机IV向量（Base64编码）
$iv = SimpleCrypto::generateIv();
// 示例结果："rJ6f8eA3bZ9cX7dY4"
```

#### 步骤3：生成时间戳
```php
// 生成秒级时间戳
$ts = SimpleCrypto::generateTimestamp();
// 示例结果："1734450000"
```

#### 步骤4：AES加密
```php
// 使用AES-256-CBC加密业务数据
$dataEncrypt = SimpleCrypto::aesEncrypt($data, $iv);
// 示例结果："aBcDeFgHiJkLmNoPqRsTuVwXyZ"
```

#### 步骤5：生成签名
```php
// 使用HMAC-SHA256生成签名
$sign = SimpleCrypto::generateSign($data, $ts);
// 示例结果："5f97e8a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8"
```

## 5. 解密与验证步骤

### 5.1 解密流程

1. **接收加密参数**：接收前端发送的 `iv`、`ts`、`data_encrypt`、`sign` 参数
2. **验证时间戳**：调用 `checkTimestamp()` 验证时间戳是否在有效期内（5分钟）
3. **AES解密**：调用 `aesDecrypt()` 解密业务数据
4. **验证签名**：调用 `verifySign()` 验证数据签名是否正确
5. **返回业务数据**：解密并验证通过后，返回原始业务数据

### 5.2 代码示例

```php
/**
 * 解密验证示例
 */
public function decryptExample()
{
    // 接收前端参数
    $iv = input('iv');
    $ts = input('ts');
    $dataEncrypt = input('data_encrypt');
    $sign = input('sign');
    
    try {
        // 1. 验证时间戳
        if (!SimpleCrypto::checkTimestamp($ts)) {
            return json(['code' => 400, 'msg' => '请求已过期']);
        }
        
        // 2. AES解密
        $data = SimpleCrypto::aesDecrypt($dataEncrypt, $iv);
        
        // 3. 验证签名
        if (!SimpleCrypto::verifySign($data, $sign, $ts)) {
            return json(['code' => 400, 'msg' => '签名验证失败']);
        }
        
        // 4. 返回业务数据
        return json(['code' => 0, 'msg' => 'success', 'data' => json_decode($data, true)]);
        
    } catch (Exception $e) {
        return json(['code' => 500, 'msg' => $e->getMessage()]);
    }
}
```

## 6. 完整使用示例

### 6.1 加密示例

```php
// 导入类
use app\utils\SimpleCrypto;

// 准备业务数据
$businessData = [
    'user_id' => 123,
    'username' => 'test_user',
    'email' => 'test@example.com'
];

// 转换为JSON字符串
$data = json_encode($businessData);

try {
    // 一键生成加密参数
    $encryptResult = SimpleCrypto::encryptAll($data);
    
    // 输出结果
    var_dump($encryptResult);
    /*
    输出示例：
    array(4) {
        ["iv"]=> string(24) "rJ6f8eA3bZ9cX7dY4"
        ["ts"]=> string(10) "1734450000"
        ["data_encrypt"]=> string(64) "aBcDeFgHiJkLmNoPqRsTuVwXyZ..."
        ["sign"]=> string(64) "5f97e8a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8"
    }
    */
    
} catch (Exception $e) {
    echo "加密失败：" . $e->getMessage();
}
```

### 6.2 前后端数据传输格式

前端发起请求时，应将加密参数作为请求体发送：

```json
{
    "iv": "rJ6f8eA3bZ9cX7dY4",
    "ts": "1734450000",
    "data_encrypt": "aBcDeFgHiJkLmNoPqRsTuVwXyZ...",
    "sign": "5f97e8a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8"
}
```

## 7. 注意事项

1. **密钥安全**：
   - 密钥 `$appKey` 应妥善保管，避免泄露
   - 建议定期更换密钥
   - 前后端必须使用相同的密钥

2. **IV向量**：
   - IV向量必须是16位随机数据
   - 每次加密都应使用新的IV向量
   - IV向量需要与加密数据一起传输

3. **时间戳验证**：
   - 默认时间戳有效期为5分钟
   - 可以根据需要调整 `checkTimestamp()` 方法中的有效期

4. **签名验证**：
   - 必须验证签名，防止数据被篡改
   - 签名计算规则前后端必须一致

5. **错误处理**：
   - 所有方法都可能抛出异常，应做好异常捕获处理
   - 不要将原始错误信息直接返回给客户端

## 8. 方法列表

| 方法名 | 功能 |
|--------|------|
| `aesDecrypt` | AES-256-CBC解密 |
| `verifySign` | HMAC-SHA256签名验证 |
| `checkTimestamp` | 时间戳过期验证 |
| `generateIv` | 生成16位随机IV向量 |
| `generateTimestamp` | 生成秒级时间戳 |
| `aesEncrypt` | AES-256-CBC加密 |
| `generateSign` | 生成HMAC-SHA256签名 |
| `encryptAll` | 一键生成加密所需所有参数 |

---

**更新时间**：2025-12-16
**版本**：1.0