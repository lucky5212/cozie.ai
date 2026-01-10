# 前端加密逻辑说明文档

## 1. 概述

本文档用于说明前后端数据加密的完整逻辑，确保前后端使用一致的加密算法和参数，实现安全的数据传输。

## 2. 加密算法信息

| 项目 | 说明 |
|------|------|
| 加密算法 | AES-256-CBC |
| 签名算法 | HMAC-SHA256 |
| 密钥长度 | 32字节 (256位) |
| IV向量长度 | 16字节 (128位) |
| 编码方式 | Base64 |
| 时间戳格式 | 秒级时间戳 (10位数字) |

## 3. 前后端约定

### 3.1 共享密钥

```javascript
// 前后端必须使用相同的AppKey（32位）
const APP_KEY = '5f97e8a0b1c2d3e4f5a6b7c8d9e0f1a2';
```

### 3.2 数据传输格式

前端发送给后端的数据格式：

```json
{
    "iv": "Base64编码的16位IV向量",
    "ts": "秒级时间戳",
    "data_encrypt": "AES加密后的Base64字符串",
    "sign": "HMAC-SHA256签名（十六进制小写）"
}
```

## 4. 前端加密实现步骤

### 4.1 完整流程

1. **准备业务数据** → 2. **生成IV向量** → 3. **生成时间戳** → 4. **AES加密** → 5. **生成签名** → 6. **组装请求参数**

### 4.2 JavaScript实现示例

#### 步骤1：安装依赖

```bash
# 使用npm安装加密库
npm install crypto-js
```

#### 步骤2：完整实现代码

```javascript
import CryptoJS from 'crypto-js';

// 前后端约定的AppKey（32位）
const APP_KEY = '5f97e8a0b1c2d3e4f5a6b7c8d9e0f1a2';

class FrontendCrypto {
    /**
     * 生成16位随机IV向量（Base64编码）
     * @returns {string} Base64编码的IV向量
     */
    static generateIv() {
        // 生成16位随机数据
        const ivBytes = CryptoJS.lib.WordArray.random(16);
        // 转换为Base64编码
        return CryptoJS.enc.Base64.stringify(ivBytes);
    }

    /**
     * 生成秒级时间戳
     * @returns {string} 秒级时间戳字符串
     */
    static generateTimestamp() {
        return Math.floor(Date.now() / 1000).toString();
    }

    /**
     * AES-256-CBC加密
     * @param {string} data 原始业务数据（JSON字符串）
     * @param {string} iv Base64编码的IV向量
     * @returns {string} AES加密后的Base64字符串
     */
    static aesEncrypt(data, iv) {
        // 将Base64编码的IV转换为WordArray
        const ivBytes = CryptoJS.enc.Base64.parse(iv);
        // 使用APP_KEY作为密钥
        const keyBytes = CryptoJS.enc.Utf8.parse(APP_KEY);
        
        // AES-256-CBC加密
        const encrypted = CryptoJS.AES.encrypt(data, keyBytes, {
            iv: ivBytes,
            mode: CryptoJS.mode.CBC,
            padding: CryptoJS.pad.Pkcs7
        });
        
        // 返回Base64编码的加密结果
        return encrypted.toString();
    }

    /**
     * 生成HMAC-SHA256签名
     * @param {string} data 原始业务数据（JSON字符串）
     * @param {string} timestamp 秒级时间戳
     * @returns {string} HMAC-SHA256签名（十六进制小写）
     */
    static generateSign(data, timestamp) {
        // 签名原文：业务数据 + 时间戳
        const signStr = data + timestamp;
        // 使用APP_KEY作为密钥生成HMAC-SHA256签名
        const sign = CryptoJS.HmacSHA256(signStr, APP_KEY);
        // 转换为十六进制小写字符串
        return CryptoJS.enc.Hex.stringify(sign);
    }

    /**
     * 一键生成加密请求参数
     * @param {object} businessData 原始业务数据对象
     * @returns {object} 加密后的请求参数
     */
    static encryptRequest(businessData) {
        // 1. 将业务数据转换为JSON字符串
        const data = JSON.stringify(businessData);
        
        // 2. 生成IV向量
        const iv = this.generateIv();
        
        // 3. 生成时间戳
        const ts = this.generateTimestamp();
        
        // 4. AES加密
        const dataEncrypt = this.aesEncrypt(data, iv);
        
        // 5. 生成签名
        const sign = this.generateSign(data, ts);
        
        // 6. 组装请求参数
        return {
            iv,
            ts,
            data_encrypt: dataEncrypt,
            sign
        };
    }
}

// 使用示例
const businessData = {
    user_id: 123,
    username: 'test_user',
    action: 'login'
};

const encryptedRequest = FrontendCrypto.encryptRequest(businessData);
console.log('加密后的请求参数:', encryptedRequest);

// 发送请求到后端
// fetch('/api/endpoint', {
//     method: 'POST',
//     headers: {
//         'Content-Type': 'application/json'
//     },
//     body: JSON.stringify(encryptedRequest)
// })
```

## 5. 各步骤详细说明

### 5.1 准备业务数据

- 将需要发送的业务数据转换为JSON字符串
- 确保JSON格式正确，避免多余的空格或换行

```javascript
const businessData = { user_id: 123, username: 'test' };
const data = JSON.stringify(businessData);
```

### 5.2 生成IV向量

- IV向量必须是16位随机数据
- 每次加密都应使用新的IV向量
- IV向量需要与加密数据一起传输给后端

```javascript
// 生成16位随机IV
const ivBytes = CryptoJS.lib.WordArray.random(16);
const iv = CryptoJS.enc.Base64.stringify(ivBytes);
```

### 5.3 生成时间戳

- 使用秒级时间戳（10位数字）
- 确保前后端时间同步（允许5分钟误差）

```javascript
// 秒级时间戳
const timestamp = Math.floor(Date.now() / 1000).toString();
```

### 5.4 AES加密

- 算法：AES-256-CBC
- 密钥：32位APP_KEY
- IV向量：16位随机数据
- 填充方式：PKCS7
- 输出：Base64编码的字符串

```javascript
const encrypted = CryptoJS.AES.encrypt(
    data, 
    CryptoJS.enc.Utf8.parse(APP_KEY), 
    {
        iv: CryptoJS.enc.Base64.parse(iv),
        mode: CryptoJS.mode.CBC,
        padding: CryptoJS.pad.Pkcs7
    }
);
const dataEncrypt = encrypted.toString();
```

### 5.5 生成HMAC签名

- 算法：HMAC-SHA256
- 密钥：32位APP_KEY
- 签名原文：`业务数据JSON字符串 + 时间戳`
- 输出：十六进制小写字符串

```javascript
const signStr = data + timestamp;
const sign = CryptoJS.HmacSHA256(signStr, APP_KEY);
const signHex = CryptoJS.enc.Hex.stringify(sign);
```

## 6. 前端解密实现（可选）

如果需要前端解密后端返回的数据，可以使用以下方法：

```javascript
/**
 * AES-256-CBC解密
 * @param {string} encryptedStr Base64编码的加密字符串
 * @param {string} iv Base64编码的IV向量
 * @returns {string} 解密后的字符串
 */
static aesDecrypt(encryptedStr, iv) {
    const ivBytes = CryptoJS.enc.Base64.parse(iv);
    const keyBytes = CryptoJS.enc.Utf8.parse(APP_KEY);
    
    const decrypted = CryptoJS.AES.decrypt(
        encryptedStr, 
        keyBytes, 
        {
            iv: ivBytes,
            mode: CryptoJS.mode.CBC,
            padding: CryptoJS.pad.Pkcs7
        }
    );
    
    return decrypted.toString(CryptoJS.enc.Utf8);
}
```

## 7. 注意事项

### 7.1 安全性要求

1. **密钥保护**：
   - 不要将密钥硬编码在前端代码中（生产环境建议通过安全方式获取）
   - 避免在日志中打印密钥
   - 定期更换密钥

2. **IV向量**：
   - 每次加密必须使用新的IV向量
   - IV向量不需要保密，但需要与加密数据一起传输

3. **时间戳**：
   - 确保前端时间准确（可以考虑从后端同步时间）
   - 时间戳用于防止重放攻击

### 7.2 错误处理

1. **参数验证**：
   - 确保业务数据格式正确
   - 验证生成的IV向量长度为16字节

2. **异常捕获**：
   - 捕获加密过程中可能出现的异常
   - 提供友好的错误提示

### 7.3 性能考虑

1. **加密频率**：
   - 只加密敏感数据
   - 避免不必要的加密操作

2. **依赖库**：
   - 使用成熟的加密库（如CryptoJS）
   - 考虑使用轻量化的加密库减少包体积

## 8. 测试验证

### 8.1 本地测试

1. 使用前端代码生成加密数据
2. 使用后端代码解密并验证
3. 确保解密后的数据与原始数据一致

### 8.2 联调测试

1. 前端发送加密请求到后端
2. 后端验证并处理请求
3. 检查响应结果是否正确

## 9. 常见问题

### Q1: 为什么加密后的数据长度不一致？
A: AES加密后的数据长度是16字节的整数倍，加上Base64编码会增加约33%的长度，这是正常现象。

### Q2: 为什么后端提示签名验证失败？
A: 可能的原因：
- 前后端APP_KEY不一致
- 签名原文拼接顺序错误
- 时间戳格式不正确
- 编码方式不一致（如Base64的URL安全编码）

### Q3: 为什么IV向量必须是16位？
A: AES-256-CBC算法要求IV向量长度必须与分组长度相同（128位=16字节）。

### Q4: 如何处理长数据的加密？
A: AES算法可以处理任意长度的数据，CryptoJS会自动进行分块和填充处理。

## 10. 版本信息

| 版本 | 更新内容 | 更新时间 |
|------|----------|----------|
| v1.0 | 初始版本 | 2025-12-16 |

---

**文档作者**：后端开发团队  
**适用范围**：前端开发人员