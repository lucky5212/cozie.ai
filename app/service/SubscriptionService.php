<?php
// app/service/SubscriptionService.php
namespace app\service;

use app\model\MoneyLog;
use app\model\SubscriptionNotification;
use app\model\UserSubscription;
use app\model\User;
use Exception;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Log;
use think\facade\Db;
use think\facade\Log as FacadeLog;
use think\facade\Config;

class SubscriptionService
{

    // 不再需要手动判断环境，使用环境变量配置的URL

    // 验证苹果收据
    public function verifyAppleReceipt($receipt, $userId, $order = null)
    {
        // 从环境变量获取验证URL
        $url = Config::get('apple.apple_verify_url');

        // 从环境变量获取共享密钥
        $sharedSecret = Config::get('apple.apple_shared_secret');

        // 构建请求数据
        $data = [
            'receipt-data' => $receipt,
            'password' => $sharedSecret // 如果是自动续订订阅需要
        ];

        // 发送请求到苹果服务器
        $client = HttpClient::create();
        try {
            $response = $client->request('POST', $url, [
                'json' => $data,
                'timeout' => 30,
            ]);

            $content = $response->getContent();
            $result = json_decode($content, true);
        } catch (ClientException | TransportException $e) {
            throw new Exception('请求苹果服务器失败: ' . $e->getMessage());
        }

        if ($result['status'] != 0) {
            throw new Exception('收据验证失败: ' . $result['status']);
        }

        // 处理验证结果
        return $this->processReceiptResult($result, $userId, $receipt, $order);
    }

    // 处理收据验证结果
    private function processReceiptResult($result, $userId, $receipt, $order = null)
    {
        $latestReceiptInfo = end($result['latest_receipt_info']);

        // 解析订阅信息
        $productId = $latestReceiptInfo['product_id'];
        $transactionId = $latestReceiptInfo['transaction_id'];
        $originalTransactionId = $latestReceiptInfo['original_transaction_id'];
        $purchaseDate = date('Y-m-d H:i:s', $latestReceiptInfo['purchase_date_ms'] / 1000);
        $expiresDate = date('Y-m-d H:i:s', $latestReceiptInfo['expires_date_ms'] / 1000);
        $isActive = $latestReceiptInfo['expires_date_ms'] > time() * 1000;
        $autoRenewStatus = $latestReceiptInfo['auto_renew_status'] ?? 1;

        // 如果有订单，验证产品ID是否匹配
        if ($order && $order['product_id'] != $productId) {
            throw new Exception('产品ID不匹配');
        }

        // 保存或更新用户订阅信息
        $subscriptionModel = new UserSubscription();
        $existingSubscription = $subscriptionModel->where([
            'user_id' => $userId,
            'original_transaction_id' => $originalTransactionId
        ])->find();

        if ($existingSubscription) {
            // 更新现有订阅
            $existingSubscription->save([
                'receipt' => $receipt,
                'expires_date' => $expiresDate,
                'is_active' => $isActive ? 1 : 0,
                'auto_renew_status' => $autoRenewStatus,
                'status' => $isActive ? 1 : 3,
                'update_time' => date('Y-m-d H:i:s')
            ]);
        } else {
            // 创建新订阅
            $subscriptionModel->save([
                'user_id' => $userId,
                'product_id' => $productId,
                'transaction_id' => $transactionId,
                'original_transaction_id' => $originalTransactionId,
                'receipt' => $receipt,
                'expires_date' => $expiresDate,
                'is_active' => $isActive ? 1 : 0,
                'auto_renew_status' => $autoRenewStatus,
                'status' => $isActive ? 1 : 3,
                'create_time' => date('Y-m-d H:i:s')
            ]);
        }

        // 授予用户订阅权益（如增加钻石、解锁功能等）
        $this->grantSubscriptionBenefits($userId, $productId);
        // 记录用户订阅历史
        Db::name('user_purchase')->insert([
            'user_id' => $userId,
            'product_id' => $productId,
            'transaction_id' => $transactionId,
            'order_no' => $order['order_no'] ?? null,
            'amount' => $order['amount'] ?? 0,
            'currency' => $order['currency'] ?? 'CNY',
            'receipt' => $receipt,
            'purchase_date' => $purchaseDate,
            'type' => 1, // 1=订阅
            'status' => 1, // 已支付
            'create_time' => date('Y-m-d H:i:s')
        ]);

        // 返回包含交易ID和购买日期的结果
        return [
            'product_id' => $productId,
            'transaction_id' => $transactionId,
            'original_transaction_id' => $originalTransactionId,
            'expires_date' => $expiresDate,
            'purchase_date' => $purchaseDate,
            'is_active' => $isActive
        ];
    }

    // 验证苹果单次购买收据
    public function verifyPurchaseReceipt($receipt, $userId, $productId)
    {
        try {
            // 从环境变量获取验证URL
            $url = Config::get('apple.apple_verify_url');

            // 构建请求数据
            $data = [
                'receipt-data' => $receipt
            ];

            // 单次购买不需要共享密钥，但为了一致性可以保留（苹果会忽略不需要的参数）
            $sharedSecret = Config::get('apple.apple_shared_secret');
            if (!empty($sharedSecret)) {
                $data['password'] = $sharedSecret;
            }

            // 发送请求到苹果服务器
            $client = HttpClient::create();
            $response = $client->request('POST', $url, [
                'json' => $data,
                'timeout' => 30,
            ]);

            $content = $response->getContent();
            $result = json_decode($content, true);

            if ($result['status'] != 0) {
                throw new Exception('收据验证失败: ' . $result['status']);
            }

            // 处理验证结果
            return $this->processPurchaseReceiptResult($result, $userId, $productId, $receipt);
        } catch (ClientException | TransportException $e) {
            throw new Exception('请求苹果服务器失败: ' . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception('验证收据失败: ' . $e->getMessage());
        }
    }

    // 处理单次购买收据验证结果
    private function processPurchaseReceiptResult($result, $userId, $productId, $receipt)
    {
        // 获取最新的交易信息
        $latestReceiptInfo = end($result['receipt']['in_app']);

        // 验证产品ID是否匹配
        if ($latestReceiptInfo['product_id'] != $productId) {
            throw new Exception('产品ID不匹配');
        }

        // 获取交易信息
        $transactionId = $latestReceiptInfo['transaction_id'];
        $purchaseDate = date('Y-m-d H:i:s', $latestReceiptInfo['purchase_date_ms'] / 1000);

        // 检查是否已经处理过该交易
        $existingPurchase = Db::name('user_purchase')
            ->where('transaction_id', $transactionId)
            ->find();

        if ($existingPurchase) {
            // 已经处理过，直接返回
            return [
                'transaction_id' => $transactionId,
                'product_id' => $productId,
                'purchase_date' => $purchaseDate,
                'processed' => true
            ];
        }

        // 授予用户购买的商品（例如：增加钻石）
        $this->grantPurchaseBenefits($userId, $productId);

        return [
            'transaction_id' => $transactionId,
            'product_id' => $productId,
            'purchase_date' => $purchaseDate,
            'processed' => false
        ];
    }

    // 授予用户购买的商品权益
    private function grantPurchaseBenefits($userId, $productId)
    {
        // 从数据库获取产品信息
        $product = Db::name('apple_product')
            ->where('product_id', $productId)
            ->find();

        if (!$product) {
            throw new Exception('产品不存在');
        }

        // 获取钻石数量（从产品配置中获取）
        $diamondAmount = $product['num'] ?? 0;

        if ($diamondAmount > 0) {
            // 使用事务确保数据一致性
            Db::transaction(function () use ($userId, $diamondAmount, $product) {
                // 增加用户钻石
                $user = Db::name('user')
                    ->where('id', $userId)
                    ->find();

                if (!$user) {
                    throw new Exception('用户不存在');
                }
                $lang = $user['lang'];
                $oldMoney = $user['money'];
                $newMoney = $oldMoney + $diamondAmount;

                // 更新用户钻石
                Db::name('user')
                    ->where('id', $userId)
                    ->update(['money' => $newMoney]);

                $text = lang_content('money_log.invite_reward', $lang, 'diamond_reward');
                // 记录钻石日志
                $moneyLogModel = new MoneyLog();
                $moneyLogModel->addMoneyLog(
                    $userId,
                    $diamondAmount,
                    $oldMoney,
                    $newMoney,
                    $text
                );
            });
        }
    }

    // 处理苹果服务器通知
    public function handleAppleNotification($rawData)
    {
        $notification = json_decode($rawData, true);

        // 保存通知日志
        $notificationModel = new SubscriptionNotification();
        $notificationModel->save([
            'notification_type' => $notification['notification_type'] ?? '',
            'subtype' => $notification['subtype'] ?? '',
            'original_transaction_id' => $notification['data']['latest_receipt_info']['original_transaction_id'],
            'transaction_id' => $notification['data']['latest_receipt_info']['transaction_id'],
            'notification_data' => $rawData,
            'create_time' => date('Y-m-d H:i:s')
        ]);

        // 根据通知类型处理
        switch ($notification['notification_type']) {
            case 'INITIAL_BUY':
                // 初始购买
                break;
            case 'RENEWAL':
                // 续订成功
                $this->handleRenewalNotification($notification);
                break;
            case 'CANCEL':
                // 订阅取消
                // $this->handleCancelNotification($notification);
                break;
            case 'EXPIRED':
                // 订阅过期
                // $this->handleExpiredNotification($notification);
                break;
        }
        // 更新通知处理状态
        $notificationModel->save(['status' => 1, 'process_time' => date('Y-m-d H:i:s')]);
    }

    // 处理订阅续订通知
    public function handleRenewalNotification($notification)
    {
        try {
            // 解析通知中的订阅信息
            $latestReceiptInfo = $notification['data']['latest_receipt_info'];
            $transactionId = $latestReceiptInfo['transaction_id'];
            $originalTransactionId = $latestReceiptInfo['original_transaction_id'];
            $productId = $latestReceiptInfo['product_id'];
            $purchaseDate = date('Y-m-d H:i:s', strtotime($latestReceiptInfo['purchase_date']));
            $expiresDate = date('Y-m-d H:i:s', strtotime($latestReceiptInfo['expires_date']));
            $autoRenewStatus = $latestReceiptInfo['auto_renew_status'] ?? 1;
            $receipt = $notification['data']['latest_receipt'] ?? '';

            // 根据原始交易ID查找用户订阅记录
            $subscription = Db::name('user_subscription')
                ->where('original_transaction_id', $originalTransactionId)
                ->find();

            if (!$subscription) {
                throw new Exception('未找到对应的订阅记录');
            }

            $userId = $subscription['user_id'];
            // 使用事务确保数据一致性
            Db::transaction(function () use ($userId, $productId, $transactionId, $originalTransactionId, $expiresDate, $autoRenewStatus, $receipt) {
                // 更新订阅记录
                Db::name('user_subscription')
                    ->where('original_transaction_id', $originalTransactionId)
                    ->update([
                        'product_id' => $productId,
                        'transaction_id' => $transactionId,
                        'expires_date' => $expiresDate,
                        'is_active' => 1, // 激活订阅
                        'auto_renew_status' => $autoRenewStatus,
                        'status' => 1, // 正常状态
                        'update_time' => date('Y-m-d H:i:s'),
                        'receipt' => $receipt
                    ]);

                // 授予用户订阅权益
                $this->grantSubscriptionBenefits($userId, $productId);
            });

            // 记录日志
            FacadeLog::info('订阅续订成功 - 用户ID: ' . $userId . ', 产品ID: ' . $productId . ', 过期时间: ' . $expiresDate);
        } catch (Exception $e) {
            FacadeLog::error('处理订阅续订通知失败: ' . $e->getMessage());
            throw new Exception('处理订阅续订通知失败: ' . $e->getMessage());
        }
    }

    // 获取用户订阅状态
    public function getSubscriptionStatus($userId)
    {
        try {
            // 查询用户的所有订阅记录
            $subscriptions = Db::name('user_subscription')
                ->where('user_id', $userId)
                ->order('create_time', 'desc')
                ->select();

            if (empty($subscriptions)) {
                return [
                    'has_active_subscription' => false,
                    'current_subscription' => null,
                    'all_subscriptions' => []
                ];
            }

            // 查找活跃的订阅
            $activeSubscription = null;
            foreach ($subscriptions as &$subscription) {
                // 更新订阅的活跃状态
                $subscription['is_active'] = strtotime($subscription['expires_date']) > time() ? 1 : 0;
                $subscription['status'] = $subscription['is_active'] ? 1 : 3; // 1=正常, 3=已过期

                // 更新数据库中的状态
                Db::name('user_subscription')
                    ->where('id', $subscription['id'])
                    ->update([
                        'is_active' => $subscription['is_active'],
                        'status' => $subscription['status'],
                        'update_time' => date('Y-m-d H:i:s')
                    ]);

                // 找到第一个活跃的订阅
                if ($subscription['is_active'] && !$activeSubscription) {
                    $activeSubscription = $subscription;
                }
            }

            return [
                'has_active_subscription' => !empty($activeSubscription),
                'current_subscription' => $activeSubscription,
                'all_subscriptions' => $subscriptions
            ];
        } catch (Exception $e) {
            // 记录错误日志
            FacadeLog::error('获取用户订阅状态失败: ' . $e->getMessage());

            // 返回默认值
            return [
                'has_active_subscription' => false,
                'current_subscription' => null,
                'all_subscriptions' => []
            ];
        }
    }

    // 授予用户订阅权益
    private function grantSubscriptionBenefits($userId, $productId)
    {
        // 查询用户信息
        $user = Db::name('user')->where('id', $userId)->find();
        if (!$user) {
            throw new Exception('用户不存在');
        }
        $lang = $user['lang'];

        $apple_product = Db::name('ai_apple_product')->where('product_id', $productId)->find();
        if (!$apple_product) {
            throw new Exception('苹果产品不存在');
        }
        $diamondAmount = $apple_product['num'];
        Db::name('user')->where('id', $userId)->inc('money', $diamondAmount)->update();
        // 记录日志
        $moneyLogModel = new MoneyLog();
        $moneyLogModel->addMoneyLog(
            $userId,
            $diamondAmount, // 根据产品确定金额
            $user['money'], // 当前金额
            $user['money'] + $diamondAmount, // 新金额
            lang_content('money_log.subscription_reward', $user['lang'], '订阅奖励')
        );
    }
}
