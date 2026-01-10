<?php
// app/service/OpenRouterService.php
namespace app\service;

use OpenAI\Client;
use OpenAI\Factory;
use think\facade\Config;
use think\Exception;
use think\facade\Log as FacadeLog;
use think\facade\Env;

class OpenRouterService
{
    // 1. 私有静态属性：存储唯一的客户端实例
    private static ?Client $client = null;

    // 2. 私有构造方法：禁止外部 new 实例
    private function __construct()
    {
        // 空实现，防止外部实例化
    }

    // 3. 私有克隆方法：禁止外部克隆实例（允许 private）
    private function __clone()
    {
        // 空实现，防止克隆
    }

    // 4. 公共反序列化方法：必须 public，内部抛出异常禁止反序列化
    public function __wakeup()
    {
        throw new Exception('禁止反序列化单例实例');
    }

    /**
     * 5. 公共静态方法：提供全局访问点，获取唯一的 OpenRouter 客户端实例
     * @return Client
     * @throws Exception
     */
    public static function getClient(): Client
    {
        // 检查实例是否已存在，不存在则创建
        if (self::$client === null) {
            // 加载 OpenRouter 配置
            $config = Config::get('openrouter');

            // 验证核心配置是否存在
            if (empty($config['api_key'])) {
                FacadeLog::error('OpenRouter API Key 未配置');
                throw new Exception('OpenRouter API Key 未配置');
            }

            // 检查必要的请求头配置
            if (empty($config['extra_headers']['HTTP-Referer'])) {
                FacadeLog::warning('OpenRouter HTTP-Referer 未配置，可能导致 API 访问限制');
            }
            if (empty($config['extra_headers']['X-Title'])) {
                FacadeLog::warning('OpenRouter X-Title 未配置，可能导致 API 访问限制');
            }

            // 创建 HTTP 客户端（Symfony HttpClient 为例，支持代理和超时）
            $httpClient = new \Symfony\Component\HttpClient\Psr18Client(\Symfony\Component\HttpClient\HttpClient::create([
                'timeout' => $config['http_client']['timeout'],
                //  'proxy' => !empty($config['http_client']['proxy']) ? $config['http_client']['proxy'] : null,
            ]));

            // 创建 OpenAI 客户端（适配 OpenRouter）
            $factory = new Factory();
            $factory = $factory->withApiKey($config['api_key'])
                ->withBaseUri($config['base_url'])
                ->withHttpClient($httpClient);

            // 添加所有额外的 HTTP 头
            foreach ($config['extra_headers'] as $name => $value) {
                // 只添加非空的请求头
                if (!empty($value)) {
                    $factory = $factory->withHttpHeader($name, $value);
                }
            }

            // 完成客户端创建
            self::$client = $factory->make();
        }

        // 返回唯一实例
        return self::$client;
    }

    /**
     * 可选：封装常用的聊天方法，简化控制器调用
     * @param array $messages 对话消息列表
     * @param string $model 模型名称
     * @param float $temperature 随机性
     * @param int $maxRetries 最大重试次数
     * @param int $initialDelayMs 初始重试延迟（毫秒）
     * @return string 响应内容
     * @throws \Exception
     */
    public static function chat(array $messages, string $model = "anthropic/claude-3.7-sonnet,openrouter/openai/gpt-4o-mini,openrouter/openai/gpt-4o", float $temperature = 0.7, int $maxRetries = 5, int $initialDelayMs = 1500): string
    {
        // 将模型字符串拆分为数组，支持逗号分隔的多个模型
        $models = array_map('trim', explode(',', $model));
        if (empty($models)) {
            throw new Exception('请至少指定一个模型');
        }

        $allErrors = [];

        // 遍历所有模型，依次尝试
        foreach ($models as $currentModel) {
            $attempt = 0;
            $modelSuccess = false;

            while ($attempt <= $maxRetries) {
                try {
                    $client = self::getClient();
                    $response = $client->chat()->create([
                        'model' => $currentModel,
                        'messages' => $messages,
                        'temperature' => $temperature,
                    ]);

                    $content = $response->choices[0]->message->content;

                    if ($attempt > 0) {
                        FacadeLog::info('OpenRouter 对话请求重试成功', [
                            'model' => $currentModel,
                            'attempt' => $attempt,
                            'content' => $content
                        ]);
                    } else {
                        FacadeLog::info('OpenRouter 对话请求成功', [
                            'model' => $currentModel,
                            'content' => $content
                        ]);
                    }
                    return $content;
                } catch (\OpenAI\Exceptions\ErrorException $e) {
                    $attempt++;
                    $errorStatus = $e->getStatusCode();
                    $errorType = $e->getErrorType();
                    $errorMessage = $e->getMessage();
                    $errorCode = $e->getErrorCode();
                    // 获取响应体内容
                    $responseBody = $e->response->getBody()->getContents();

                    // 判断是否可以重试
                    $isRetryable = self::isRetryableError($errorStatus, $errorType);

                    // 记录详细错误日志
                    $logData = [
                        'error_class' => get_class($e),
                        'error_message' => $errorMessage,
                        'error_code' => $errorCode,
                        'http_status_code' => $errorStatus,
                        'openai_error_type' => $errorType,
                        'response_body' => $responseBody,
                        'model' => $currentModel,
                        'temperature' => $temperature,
                        'messages_count' => count($messages),
                        'messages_sample' => $messages ? json_encode(array_slice($messages, -2)) : '[]',
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                        'retryable' => $isRetryable,
                        'api_key_configured' => !empty(Config::get('openrouter.api_key')),
                        'referer_configured' => !empty(Config::get('openrouter.extra_headers.HTTP-Referer')),
                        'referer_value' => Config::get('openrouter.extra_headers.HTTP-Referer'),
                        'title_value' => Config::get('openrouter.extra_headers.X-Title')
                    ];
                    // 将日志数据转换为字符串格式，确保所有信息都能被记录
                    $logString = "OpenRouter API错误详情：";
                    foreach ($logData as $key => $value) {
                        $logString .= "{$key}: " . (is_array($value) || is_object($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value) . "";
                    }

                    if ($isRetryable && $attempt <= $maxRetries) {
                        FacadeLog::warning('OpenRouter 对话请求失败 - API错误（将重试）', $logData);
                        // 同时使用字符串格式记录完整日志信息
                        FacadeLog::warning($logString);
                        // 指数退避延迟 + 随机抖动 (10-20%的随机值)
                        $delayMs = $initialDelayMs * (2 ** ($attempt - 1));
                        // 添加随机抖动，避免请求峰值
                        $jitter = $delayMs * (0.1 + (mt_rand(0, 100) / 1000)); // 10-20%随机抖动
                        $delayMs = $delayMs + $jitter;
                        // 限制最大延迟为45秒
                        $delayMs = min($delayMs, 45000);
                        // 等待延迟：将毫秒转换为微秒 (1毫秒 = 1000微秒)
                        usleep((int)$delayMs * 1000);
                    } else {
                        FacadeLog::error('OpenRouter 对话请求失败 - API错误（已达最大重试次数或不可重试）', $logData);
                        // 同时使用字符串格式记录完整日志信息
                        FacadeLog::error($logString);
                        // 记录当前模型的所有错误
                        $allErrors[] = [
                            'model' => $currentModel,
                            'error' => "API错误: {$errorMessage}",
                            'code' => $errorCode
                        ];
                        break; // 当前模型所有重试都失败，尝试下一个模型
                    }
                } catch (\Exception $e) {
                    $attempt++;
                    // 记录系统错误日志
                    $logData = [
                        'error_class' => get_class($e),
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'model' => $currentModel,
                        'temperature' => $temperature,
                        'messages_count' => count($messages),
                        'messages_sample' => $messages ? json_encode(array_slice($messages, -2)) : '[]',
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                        'stack_trace' => $e->getTraceAsString(),
                        'api_key_configured' => !empty(Config::get('openrouter.api_key')),
                        'referer_configured' => !empty(Config::get('openrouter.extra_headers.HTTP-Referer')),
                        'referer_value' => Config::get('openrouter.extra_headers.HTTP-Referer'),
                        'title_value' => Config::get('openrouter.extra_headers.X-Title')
                    ];
                    // 将日志数据转换为字符串格式，确保所有信息都能被记录
                    $logString = "OpenRouter 系统错误详情：";
                    foreach ($logData as $key => $value) {
                        $logString .= "{$key}: " . (is_array($value) || is_object($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value) . "";
                    }

                    // 网络连接错误可以重试
                    $errorMessage = strtolower($e->getMessage());
                    $networkErrorPattern = '/(connection|timeout|curl error|network error|could not connect|timed out|connection refused|connection reset|dns lookup failed|socket error|read timeout|write timeout)/i';
                    $isNetworkError = preg_match($networkErrorPattern, $errorMessage) === 1;

                    if ($isNetworkError && $attempt <= $maxRetries) {
                        FacadeLog::warning('OpenRouter 对话请求失败 - 网络错误（将重试）', $logData);
                        // 同时使用字符串格式记录完整日志信息
                        FacadeLog::warning($logString);
                        // 指数退避延迟 + 随机抖动
                        $delayMs = $initialDelayMs * (2 ** ($attempt - 1));
                        $jitter = $delayMs * (0.1 + (mt_rand(0, 100) / 1000));
                        $delayMs = $delayMs + $jitter;
                        $delayMs = min($delayMs, 45000);
                        usleep((int)$delayMs * 1000);
                    } else {
                        // 其他系统错误不可重试
                        FacadeLog::error('OpenRouter 对话请求失败 - 系统错误', $logData);
                        // 同时使用字符串格式记录完整日志信息
                        FacadeLog::error($logString);
                        // 记录当前模型的所有错误
                        $allErrors[] = [
                            'model' => $currentModel,
                            'error' => "系统错误: {$e->getMessage()}",
                            'code' => $e->getCode()
                        ];
                        break; // 当前模型所有重试都失败，尝试下一个模型
                    }
                }
            }
        }

        // 所有模型都失败，构建最终错误信息
        $errorSummary = [];
        foreach ($allErrors as $error) {
            $errorSummary[] = "模型 {$error['model']}: {$error['error']}";
        }

        $finalErrorMessage = 'OpenRouter 对话请求失败: 所有模型尝试均失败。错误详情: ' . implode('; ', $errorSummary);
        FacadeLog::error('OpenRouter 所有模型尝试均失败', ['errors' => $allErrors]);

        // 添加1秒延迟后再返回错误
        usleep(1000000);
        throw new Exception($finalErrorMessage);
    }

    /**
     * 判断错误是否可以重试
     * @param int|null $statusCode HTTP状态码
     * @param string|null $errorType OpenAI错误类型
     * @return bool
     */
    private static function isRetryableError(?int $statusCode, ?string $errorType): bool
    {
        // 可重试的HTTP状态码
        $retryableStatusCodes = [
            429, // 请求过多（限流）
            500, // 服务器内部错误
            502, // 网关错误
            503, // 服务不可用
            504, // 网关超时
            507, // 服务器存储不足
            508, // 检测到无限循环
            520, // 未知错误
            521, // Web服务器已关闭
            522, // 连接超时
            523, // 源服务器不可达
            524, // 连接超时
            525, // SSL握手失败
            526, // SSL证书无效
            527, // Railgun错误
            530, // 源服务器错误
            408, // 请求超时
            409, // 冲突（可能是临时的）
            423, // 资源锁定
            501, // 未实现（可能是临时错误）
            505, // HTTP版本不支持（可能是临时错误）
            510, // 未扩展（可能是临时错误）
            511  // 网络认证要求（可能是临时错误）
        ];

        // 可重试的错误类型
        $retryableErrorTypes = [
            'rate_limit_error',        // 限流错误
            'server_error',            // 服务器错误
            'api_connection_error',    // API连接错误
            'timeout_error',           // 超时错误
            'service_unavailable',     // 服务不可用
            'gateway_timeout',         // 网关超时
            'internal_server_error',   // 内部服务器错误
            'bad_gateway',             // 网关错误
            'network_error',           // 网络错误
            'request_timeout',         // 请求超时
            'too_many_requests',       // 请求过多
            'temporary_unavailable',   // 临时不可用
            'service_overloaded',      // 服务过载
            'connection_error',        // 连接错误
            'try_again_later',         // 请稍后重试
            'resource_exhausted',      // 资源耗尽
            'transient_error',         // 临时错误
            'aborted',                 // 请求中止
            'unavailable',             // 不可用
            'busy',                    // 服务繁忙
            'slow_down'                // 请减速
        ];

        // 检查状态码或错误类型是否可重试
        $statusRetryable = in_array($statusCode, $retryableStatusCodes);
        $typeRetryable = is_string($errorType) && in_array(strtolower($errorType), array_map('strtolower', $retryableErrorTypes));

        // 额外检查：如果状态码是5xx系列且不在列表中，也尝试重试
        $is5xxError = is_int($statusCode) && $statusCode >= 500 && $statusCode < 600;

        return $statusRetryable || $typeRetryable || $is5xxError;
    }
}
