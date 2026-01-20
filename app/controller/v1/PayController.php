<?php

namespace app\controller\v1;

use app\BaseController;
use app\common\JwtAuth;
use app\service\SubscriptionService;
use Exception;
use think\facade\Db;
use think\facade\Log as FacadeLog;
use think\exception\ValidateException;

// 订单状态常量
const ORDER_STATUS_PENDING = 0;  // 待支付
const ORDER_STATUS_PAID = 1;      // 已支付
const ORDER_STATUS_CANCELLED = 2; // 已取消
const ORDER_STATUS_REFUNDED = 3;  // 已退款

// 产品类型常量
const PRODUCT_TYPE_SUBSCRIPTION = 1; // 订阅产品
const PRODUCT_TYPE_SINGLE = 2;       // 单次支付产品

// 订单类型常量
const ORDER_TYPE_SUBSCRIPTION = 1; // 订阅订单
const ORDER_TYPE_PURCHASE = 2;      // 单次支付订单

class PayController extends BaseController
{


    /**
     * 验证用户身份
     */
    private function validateToken()
    {
        $token = JwtAuth::decodeToken($this->request->header('Access-Token'));
        if (!$token) {
            throw new ValidateException('未授权', 401);
        }
        return $token;
    }

    /**
     * 发起订阅 - 获取可用的订阅产品列表
     */
    public function getSubscriptionProducts()
    {
        try {
            // 验证用户身份
            $token = $this->validateToken();

            // 查询可用的订阅产品
            $products = Db::name('apple_product')
                ->where('status', 1) // 只返回启用的产品
                ->where('type', PRODUCT_TYPE_SUBSCRIPTION) // 只返回订阅产品
                ->order('price', 'asc') // 按价格升序排列
                ->select();

            // 如果没有产品，返回空列表
            if (empty($products)) {
                return json([
                    'code' => 200,
                    'msg' => '暂无可用的订阅产品',
                    'data' => [
                        'products' => []
                    ]
                ]);
            }

            // 获取用户的当前订阅状态
            $subscriptionService = new SubscriptionService();
            $subscriptionStatus = $subscriptionService->getSubscriptionStatus($token['uid']);
            return json([
                'code' => 200,
                'msg' => '获取订阅产品成功',
                'data' => [
                    'products' => $products,
                    'subscription_status' => $subscriptionStatus
                ]
            ]);
        } catch (Exception $e) {
            // 记录错误日志
            FacadeLog::error('获取订阅产品失败: ' . $e->getMessage());

            return json([
                'code' => 500,
                'msg' => '获取订阅产品失败: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * 创建支付订单
     */
    public function createOrder()
    {
        try {
            $productId = $this->request->param('product_id', '', 'trim');

            // 验证用户身份
            $token = $this->validateToken();
            $userId = $token['uid'];

            // 验证产品是否存在
            $product = Db::name('apple_product')
                ->where('product_id', $productId)
                ->where('status', 1)
                ->where('type', PRODUCT_TYPE_SINGLE) // 只允许单次支付产品
                ->find();

            if (!$product) {
                throw new ValidateException('产品不存在或已下架');
            }
            // 生成订单号
            $orderNo = $this->generateOrderNo();
            // 创建订单记录
            $orderId = Db::name('user_payment_order')->insertGetId([
                'order_no' => $orderNo,
                'user_id' => $userId,
                'product_id' => $productId,
                'amount' => $product['price'],
                'currency' => $product['currency'],
                'status' => ORDER_STATUS_PENDING,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
                'order_type' => ORDER_TYPE_PURCHASE
            ]);
        } catch (Exception $e) {
            // 记录错误日志
            FacadeLog::error('创建支付订单失败: ' . $e->getMessage());
            return json([
                'code' => 500,
                'msg' => '创建支付订单失败: ' . $e->getMessage()
            ]);
        }
        return json([
            'code' => 200,
            'msg' => '订单创建成功',
            'data' => [
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'product_id' => $productId,
                'amount' => $product['price']
            ]
        ]);
    }


    // 生成唯一订单号
    private function generateOrderNo()
    {
        // 使用更安全的订单号生成方式：时间戳 + 用户ID + 随机数
        return date('YmdHis') .
            substr(uniqid(), -8) .
            str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
    /**
     * 获取单次支付产品列表
     */
    public function getProducts()
    {
        try {
            // 验证用户身份
            $token = $this->validateToken();

            // 查询可用的单次支付产品
            $products = Db::name('apple_product')
                ->where('status', 1) // 只返回启用的产品
                ->where('type', PRODUCT_TYPE_SINGLE) // 只返回单次支付产品
                ->order('price', 'asc') // 按价格升序排列
                ->field('id, product_id, name, description, price, virtual_price, currency')
                ->select();

            // 如果没有产品，返回空列表
            if (empty($products)) {
                return json([
                    'code' => 200,
                    'msg' => '暂无可用的支付产品',
                    'data' => [
                        'products' => []
                    ]
                ]);
            }

            return json([
                'code' => 200,
                'msg' => '获取支付产品成功',
                'data' => [
                    'products' => $products
                ]
            ]);
        } catch (Exception $e) {
            // 记录错误日志
            FacadeLog::error('获取支付产品失败: ' . $e->getMessage());

            return json([
                'code' => 500,
                'msg' => '获取支付产品失败: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * 验证苹果单次支付收据
     */
    public function verifyReceiptWithOrder()
    {
        try {
            $receipt = $this->request->param('receipt', '', 'trim');
            $orderNo = $this->request->param('order_no', '', 'trim');

            // 验证参数
            if (empty($receipt) || empty($orderNo)) {
                throw new ValidateException('参数不完整');
            }

            // 使用事务和行锁确保并发安全
            $result = Db::transaction(function () use ($receipt, $orderNo) {
                // 查询订单并加锁
                $order = Db::name('user_payment_order')
                    ->where('order_no', $orderNo)
                    ->lock(true) // 加行锁
                    ->find();

                if (!$order) {
                    throw new ValidateException('订单不存在');
                }

                // 检查订单状态
                if ($order['status'] == ORDER_STATUS_PAID) {
                    return [
                        'success' => true,
                        'message' => '订单已支付',
                        'order' => $order
                    ];
                }

                // 验证收据
                $subscriptionService = new SubscriptionService();
                $verifyResult = $subscriptionService->verifyPurchaseReceipt($receipt, $order['user_id'], $order['product_id']);

                // 更新订单状态
                Db::name('user_payment_order')->where('order_no', $orderNo)->update([
                    'status' => ORDER_STATUS_PAID,
                    'transaction_id' => $verifyResult['transaction_id'],
                    'purchase_date' => $verifyResult['purchase_date'],
                    'receipt' => $receipt,
                    'update_time' => date('Y-m-d H:i:s')
                ]);

                // 记录用户购买历史
                Db::name('user_purchase')->insert([
                    'user_id' => $order['user_id'],
                    'product_id' => $order['product_id'],
                    'transaction_id' => $verifyResult['transaction_id'],
                    'order_no' => $orderNo,
                    'amount' => $order['amount'],
                    'currency' => $order['currency'],
                    'receipt' => $receipt,
                    'purchase_date' => $verifyResult['purchase_date'],
                    'status' => ORDER_STATUS_PAID,
                    'create_time' => date('Y-m-d H:i:s')
                ]);

                return [
                    'success' => true,
                    'message' => '订单支付成功',
                    'verify_result' => $verifyResult,
                    'order' => $order
                ];
            });

            return json([
                'code' => 200,
                'msg' => $result['message'],
                'data' => $result['verify_result'] ?? $result['order']
            ]);
        } catch (ValidateException $e) {
            return json(['code' => 400, 'msg' => $e->getMessage()]);
        } catch (Exception $e) {
            FacadeLog::error('验证收据失败: ' . $e->getMessage());
            return json(['code' => 500, 'msg' => '验证失败: ' . $e->getMessage()]);
        }
    }

    /**
     * 获取用户购买历史
     */
    public function getPurchaseHistory()
    {
        try {
            // 验证用户身份
            $token = $this->validateToken();
            $userId = $token['uid'];

            $page = $this->request->param('page', 1, 'intval');
            $limit = $this->request->param('limit', 10, 'intval');
            $type = $this->request->param('type', '', 'intval'); // 1=订阅, 2=单次支付

            // 构建查询条件
            $where = ['user_id' => $userId];
            if ($type) {
                $where['type'] = $type;
            }

            // 使用分页查询
            $purchases = Db::name('user_purchase')
                ->where($where)
                ->order('create_time', 'desc')
                ->paginate([
                    'page' => $page,
                    'list_rows' => $limit
                ]);

            // 获取分页信息
            $total = $purchases->total();

            return json([
                'code' => 200,
                'msg' => '获取购买历史成功',
                'data' => [
                    'purchases' => $purchases->items(),
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => ceil($total / $limit)
                ]
            ]);
        } catch (ValidateException $e) {
            return json(['code' => 400, 'msg' => $e->getMessage()]);
        } catch (Exception $e) {
            FacadeLog::error('获取购买历史失败: ' . $e->getMessage());
            return json(['code' => 500, 'msg' => '获取购买历史失败: ' . $e->getMessage()]);
        }
    }


    /**
     * 创建订阅订单
     */
    public function createSubscriptionOrder()
    {
        try {
            $productId = $this->request->param('product_id', '', 'trim');

            // 验证用户身份
            $token = $this->validateToken();
            $userId = $token['uid'];

            // 验证产品是否存在
            $product = Db::name('apple_product')
                ->where('product_id', $productId)
                ->where('status', 1)
                ->where('type', PRODUCT_TYPE_SUBSCRIPTION) // 只允许订阅产品
                ->find();

            if (!$product) {
                throw new ValidateException('产品不存在或已下架');
            }

            // 生成订单号
            $orderNo = $this->generateOrderNo();

            // 创建订单记录
            $orderId = Db::name('user_payment_order')->insertGetId([
                'order_no' => $orderNo,
                'user_id' => $userId,
                'product_id' => $productId,
                'amount' => $product['price'],
                'currency' => $product['currency'],
                'order_type' => ORDER_TYPE_SUBSCRIPTION, // 订阅订单类型
                'status' => ORDER_STATUS_PENDING,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s')
            ]);

            return json([
                'code' => 200,
                'msg' => '订阅订单创建成功',
                'data' => [
                    'order_id' => $orderId,
                    'order_no' => $orderNo,
                    'product_id' => $productId,
                    'amount' => $product['price'],
                    'product_name' => $product['name']
                ]
            ]);
        } catch (ValidateException $e) {
            return json(['code' => 400, 'msg' => $e->getMessage()]);
        } catch (Exception $e) {
            FacadeLog::error('创建订阅订单失败: ' . $e->getMessage());
            return json(['code' => 500, 'msg' => '创建订单失败: ' . $e->getMessage()]);
        }
    }

    /**
     * 验证订阅收据并更新订单状态
     */
    public function verifySubscriptionReceipt()
    {
        try {
            $receipt = $this->request->param('receipt', '', 'trim');
            $orderNo = $this->request->param('order_no', '', 'trim');

            // 验证参数
            if (empty($receipt) || empty($orderNo)) {
                throw new ValidateException('参数不完整');
            }

            // 使用事务和行锁确保并发安全
            $result = Db::transaction(function () use ($receipt, $orderNo) {
                // 查询订单并加锁
                $order = Db::name('user_payment_order')
                    ->where('order_no', $orderNo)
                    ->where('order_type', ORDER_TYPE_SUBSCRIPTION) // 只处理订阅订单
                    ->lock(true) // 加行锁
                    ->find();

                if (!$order) {
                    throw new ValidateException('订阅订单不存在');
                }

                // 检查订单状态
                if ($order['status'] == ORDER_STATUS_PAID) {
                    return [
                        'success' => true,
                        'message' => '订单已支付',
                        'order' => $order
                    ];
                }

                // 验证收据
                $subscriptionService = new SubscriptionService();
                $verifyResult = $subscriptionService->verifyAppleReceipt($receipt, $order['user_id'], $order);

                // 更新订单状态
                Db::name('user_payment_order')->where('order_no', $orderNo)->update([
                    'status' => ORDER_STATUS_PAID,
                    'transaction_id' => $verifyResult['transaction_id'],
                    'purchase_date' => $verifyResult['purchase_date'],
                    'receipt' => $receipt,
                    'update_time' => date('Y-m-d H:i:s')
                ]);

                return [
                    'success' => true,
                    'message' => '订阅订单支付成功',
                    'verify_result' => $verifyResult,
                    'order' => $order
                ];
            });

            return json([
                'code' => 200,
                'msg' => $result['message'],
                'data' => $result['verify_result'] ?? $result['order']
            ]);
        } catch (ValidateException $e) {
            return json(['code' => 400, 'msg' => $e->getMessage()]);
        } catch (Exception $e) {
            FacadeLog::error('验证订阅收据失败: ' . $e->getMessage());
            return json(['code' => 500, 'msg' => '验证失败: ' . $e->getMessage()]);
        }
    }


    /**
     * 查询用户订阅状态
     */
    public function getSubscriptionStatus()
    {
        try {
            // 验证用户身份
            $token = $this->validateToken();
            $userId = $token['uid'];

            // 获取订阅状态
            $subscriptionService = new SubscriptionService();
            $status = $subscriptionService->getSubscriptionStatus($userId);

            return json([
                'code' => 200,
                'msg' => '查询成功',
                'data' => $status
            ]);
        } catch (ValidateException $e) {
            return json(['code' => 400, 'msg' => $e->getMessage()]);
        } catch (Exception $e) {
            FacadeLog::error('查询订阅状态失败: ' . $e->getMessage());
            return json(['code' => 500, 'msg' => '查询失败: ' . $e->getMessage()]);
        }
    }

    /**
     * 处理苹果服务器通知
     */
    public function handleWebhook()
    {
        $rawPostData = file_get_contents('php://input');

        try {
            $subscriptionService = new SubscriptionService();
            $subscriptionService->handleAppleNotification($rawPostData);

            return json(['code' => 200, 'msg' => '处理成功']);
        } catch (Exception $e) {
            // 记录错误日志
            FacadeLog::error('处理苹果通知失败: ' . $e->getMessage());
            return json(['code' => 500, 'msg' => '处理失败']);
        }
    }
}
