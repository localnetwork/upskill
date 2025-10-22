<?php

require_once __DIR__ . '/../lib/helper.php';
require_once __DIR__ . '/../config/env.php';

use RedBeanPHP\R; // ✅ RedBean only

require_once __DIR__ . '/../models/CoursePriceTier.php';
require_once __DIR__ . '/../models/Course.php';

require_once __DIR__ . '/../models/OrderLine.php';

require_once __DIR__ . '/../controllers/AuthController.php';

class Order
{
    public static function createOrder($userId, $paymentMethod)
    {
        if (empty($userId) || !is_numeric($userId)) {
            return [
                'error'   => true,
                'status'  => 400,
                'message' => 'Invalid user ID.',
            ];
        }

        // ✅ Fetch all cart items
        $cartItems = R::find('carts', 'user_id = ?', [$userId]);
        if (count($cartItems) === 0) {
            return [
                'error'   => true,
                'status'  => 400,
                'message' => 'Cart is empty. Cannot create order.',
            ];
        }

        // ✅ Create base order
        $generatedOrderId = orderIdGenerator(10);
        $uuid = self::generateUUID();
        $totalAmount = 0;

        $order = R::dispense('orders');
        $order->user_id = $userId;
        $order->order_id = $generatedOrderId;
        $order->uuid = $uuid;
        $order->status = 'pending';
        $order->created_at = date('Y-m-d H:i:s');
        $order->updated_at = date('Y-m-d H:i:s');
        $order->payment_method = $paymentMethod;
        $orderId = R::store($order);

        // ✅ Add order lines 
        foreach ($cartItems as $item) {
            $course = R::load('courses', $item->course_id);
            $loaded = Course::viewCourseByUUID($course->uuid);
            if (!$loaded || !$loaded['data']['id']) continue;

            $price = $loaded['data']['price_tier']['price'] ?? 0;
            $totalAmount += $price;

            R::exec(
                'INSERT INTO order_lines (order_id, uuid, course_id, price) VALUES (?, ?, ?, ?)',
                [$orderId, self::generateUUID(), $loaded['data']['id'], $price]
            );
        }

        // ✅ Update total
        $order->total_amount = $totalAmount;
        R::store($order);

        // ✅ Clear cart
        R::exec('DELETE FROM carts WHERE user_id = ?', [$userId]);

        return [
            'error' => false,
            'status' => 200,
            'data' => [
                'id' => $orderId,
                'user_id' => $userId,
                'order_id' => $generatedOrderId,
                'uuid' => $uuid,
                'status' => 'pending',
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
                'total_amount' => $totalAmount,
            ],
            'message' => 'Order created successfully.'
        ];
    }

    // ✅ Pure PHP PayPal payment creation (no SDK)
    public static function createPayPalPayment($orderId, $amount)
    {
        $clientId = env('PAYPAL_CLIENT_ID');
        $secret = env('PAYPAL_CLIENT_SECRET');
        $sandbox = env('PAYPAL_SANDBOX') === 'true';
        $baseUrl = $sandbox
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';

        // ✅ Get access token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_USERPWD, "$clientId:$secret");
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        if (!$response) {
            return null;
        }

        $token = json_decode($response, true)['access_token'] ?? null;

        curl_close($ch);

        if (!$token) {
            return null;
        }

        // ✅ Create order on PayPal
        $payload = json_encode([
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $orderId,
                'amount' => [
                    'currency_code' => 'PHP',
                    'value' => number_format($amount, 2, '.', '')
                ]
            ]],
            'application_context' => [
                'return_url' => env('APP_URL') . '/checkout/success?order_id=' . $orderId,
                'cancel_url' => env('APP_URL') . '/checkout/cancel?order_id=' . $orderId,
            ]
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v2/checkout/orders');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "Authorization: Bearer $token"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        // ✅ Find PayPal approval link
        foreach ($data['links'] ?? [] as $link) {
            if ($link['rel'] === 'approve') {
                return $link['href'];
            }
        }

        return null;
    }

    public static function capturePayPalPayment($paypalOrderId)
    {
        $clientId = env('PAYPAL_CLIENT_ID');
        $secret = env('PAYPAL_CLIENT_SECRET');
        $sandbox = env('PAYPAL_SANDBOX') === 'true';
        $baseUrl = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';

        // Step 1: Get Access Token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_USERPWD, "$clientId:$secret");
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $token = json_decode($response, true)['access_token'] ?? null;
        if (!$token) {
            return [
                'error' => true,
                'message' => 'Failed to get PayPal access token.',
                'raw_response' => $response
            ];
        }

        // Step 2: Capture the PayPal Order
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v2/checkout/orders/' . $paypalOrderId . '/capture');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "Authorization: Bearer $token",
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        // Step 3: Handle possible statuses
        $orderRef = $data['purchase_units'][0]['reference_id'] ?? null;
        $status = $data['status'] ?? null;
        $captureStatus = $data['purchase_units'][0]['payments']['captures'][0]['status'] ?? null;

        // ✅ Completed payment
        if ($status === 'COMPLETED' || $captureStatus === 'COMPLETED') {
            if ($orderRef) {
                R::exec('UPDATE orders SET status = ? WHERE order_id = ?', ['completed', $orderRef]);
            }

            return [
                'error' => false,
                'message' => 'Payment completed successfully.',
                'data' => $data
            ];
        }

        // ⚠️ Cancelled or voided payment
        if (in_array($status, ['CANCELLED', 'VOIDED']) || in_array($captureStatus, ['CANCELLED', 'VOIDED'])) {
            if ($orderRef) {
                R::exec('UPDATE orders SET status = ? WHERE order_id = ?', ['cancelled', $orderRef]);
            }

            return [
                'error' => true,
                'message' => 'Payment was cancelled or voided by PayPal.',
                'data' => $data
            ];
        }

        // ⏳ Payment requires payer action or is pending
        if (in_array($status, ['PAYER_ACTION_REQUIRED', 'PENDING']) || in_array($captureStatus, ['PENDING'])) {
            return [
                'error' => true,
                'message' => 'Payment is still pending or requires action from payer.',
                'data' => $data
            ];
        }

        // ❌ Default: unknown or failed 
        return [
            'error' => true,
            'message' => 'Payment not completed or failed.',
            'data' => $data
        ];
    }


    private static function generateUUID()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    public static function getOrderByOrderId($orderId)
    {


        $order = R::findOne('orders', ' order_id = ? ', bindings: [$orderId]);

        if (!$order) {
            return [
                'error'   => true,
                'status'  => 404,
                'message' => 'Order not found.'
            ];
        }



        $orderLines = OrderLine::getOrderLinesByOrderId($order->id);

        if ($order['status'] === 'pending' && $order['payment_method'] === 'paypal') {
            // ✅ PayPal order ID (token) comes from query string
            $paypalOrderId = $_GET['token'] ?? null;

            if ($paypalOrderId) {
                $paypalResponse = self::capturePayPalPayment($paypalOrderId);
            } else {
                $paypalResponse = [
                    'error' => true,
                    'message' => 'Missing PayPal order token in query string.'
                ];
            }
        }
        // $currentUser = AuthController::getCurrentUser();

        // if ((int)$currentUser->user->id !== (int)$order['user_id']) {
        //     return [
        //         'error'   => true,
        //         'status'  => 403,
        //         'message' => 'You do not have permission to view this order.'
        //     ];
        // }


        return [
            'order'          => $order,
            'orderLines'     => $orderLines,
            'paypalResponse' => $paypalResponse ?? null,
            'test' => $order['user_id'],
        ];
    }
}
