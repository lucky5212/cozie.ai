<?php
// app/service/MiniMaxService.php
namespace app\service;

use think\facade\Config;
use think\Exception;
use think\facade\Log as FacadeLog;
use think\facade\Db;
use GuzzleHttp\Client;
use app\model\Voice;



use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;

class MiniMaxService
{
    private static ?Client $httpClient = null;

    private function __construct() {}

    private function __clone() {}

    public function __wakeup()
    {
        throw new Exception('禁止反序列化单例实例');
    }

    public static function getHttpClient(): Client
    {
        if (self::$httpClient === null) {
            $config = Config::get('minimax');

            if (empty($config['api_key'])) {
                FacadeLog::error('MiniMax API Key 未配置');
                throw new Exception('MiniMax API Key 未配置');
            }

            if (empty($config['group_id'])) {
                FacadeLog::error('MiniMax Group ID 未配置');
                throw new Exception('MiniMax Group ID 未配置');
            }

            self::$httpClient = new \GuzzleHttp\Client([
                'base_uri' => $config['tts_base_url'],
                'timeout' => $config['http_client']['timeout'],
                'headers' => [
                    'Authorization' => 'Bearer ' . $config['api_key'],
                    'Content-Type' => 'application/json',
                ],
            ]);
        }

        return self::$httpClient;
    }

    public static function textToSpeech(string $text, string $voice = null, float $speed = null, int $pitch = null): array
    {
        $config = Config::get('minimax');

        $voice = $voice ?? $config['default_voice'];
        $speed = $speed ?? $config['default_speed'];
        $pitch = $pitch ?? $config['default_pitch'];

        if (empty($text)) {
            throw new Exception('文本内容不能为空');
        }

        if (mb_strlen($text) > 500) {
            throw new Exception('文本内容不能超过500字符');
        }

        // 检查音色是否存在且可用
        $voiceModel = new Voice();
        if (!$voiceModel->isVoiceAvailable($voice)) {
            throw new Exception('不支持的音色: ' . $voice);
        }

        if ($speed < 0.5 || $speed > 2.0) {
            throw new Exception('语速必须在 0.5 到 2.0 之间');
        }

        if ($pitch < -12 || $pitch > 12) {
            throw new Exception('音调必须在 -12 到 12 之间');
        }

        try {
            $client = self::getHttpClient();

            $payload = [
                'model' => 'speech-01',
                'text' => $text,
                'voice_id' => $voice,
                'speed' => $speed,
                'pitch' => $pitch,
                'format' => 'mp3',
            ];

            FacadeLog::info('MiniMax TTS 请求开始', [
                'text_length' => mb_strlen($text),
                'voice' => $voice,
                'speed' => $speed,
                'pitch' => $pitch,
            ]);

            $response = $client->post('', [
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $responseData = json_decode($responseBody, true);

            if ($statusCode !== 200) {
                FacadeLog::error('MiniMax TTS 请求失败', [
                    'status_code' => $statusCode,
                    'response' => $responseBody,
                ]);
                throw new Exception('MiniMax TTS 请求失败: ' . ($responseData['message'] ?? '未知错误'));
            }

            if (empty($responseData['audio_url'])) {
                FacadeLog::error('MiniMax TTS 响应格式错误', [
                    'response' => $responseData,
                ]);
                throw new Exception('MiniMax TTS 响应格式错误: 未返回音频URL');
            }

            FacadeLog::info('MiniMax TTS 请求成功', [
                'audio_url' => $responseData['audio_url'],
                'duration' => $responseData['duration'] ?? null,
            ]);

            return [
                'audio_url' => $responseData['audio_url'],
                'duration' => $responseData['duration'] ?? null,
                'format' => 'mp3',
            ];
        } catch (ClientException $e) {
            $responseBody = $e->getResponse()->getBody()->getContents();
            $errorData = json_decode($responseBody, true);

            FacadeLog::error('MiniMax TTS 客户端错误', [
                'error' => $e->getMessage(),
                'response' => $responseBody,
            ]);

            throw new Exception('MiniMax TTS 客户端错误: ' . ($errorData['message'] ?? $e->getMessage()));
        } catch (RequestException $e) {
            FacadeLog::error('MiniMax TTS 请求错误', [
                'error' => $e->getMessage(),
            ]);

            throw new Exception('MiniMax TTS 请求错误: ' . $e->getMessage());
        } catch (GuzzleException $e) {
            FacadeLog::error('MiniMax TTS 网络错误', [
                'error' => $e->getMessage(),
            ]);

            throw new Exception('MiniMax TTS 网络错误: ' . $e->getMessage());
        } catch (Exception $e) {
            FacadeLog::error('MiniMax TTS 系统错误', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
