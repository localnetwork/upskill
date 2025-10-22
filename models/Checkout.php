<?php

require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../config/env.php';

use RedBeanPHP\R; // ✅ Import RedBeanPHP static facade  
class Checkout
{
    public static function processCheckout($data)
    {
        $currentUser = AuthController::getCurrentUser();

        if (!$currentUser) {
            http_response_code(401);
            return [
                'error'   => true,
                'status'  => 401,
                'message' => 'Unauthorized. Please log in first.'
            ];
        }

        // ✅ Validate payment method
        $validator  = new \Rakit\Validation\Validator;
        $validation = $validator->make($data, [
            'payment_method' => 'required',
        ]);
        $validation->validate();

        if ($validation->fails()) {
            http_response_code(422);
            return [
                'error'   => true,
                'status'  => 422,
                'errors'  => $validation->errors()->firstOfAll(),
                'message' => 'Please check the validated fields.'
            ];
        }

        // ✅ Create order
        $order = Order::createOrder(
            userId: $currentUser->user->id,
            paymentMethod: $data['payment_method']
        );

        // ✅ Ensure the order creation succeeded
        if (!isset($order['error']) || $order['error'] === true) {
            http_response_code(500);
            return [
                'error'   => true,
                'status'  => 500,
                'message' => 'Order creation failed.',
                'details' => $order
            ];
        }

        $totalAmount = $order['data']['total_amount'] ?? 0;
        $orderId     = $order['data']['order_id'] ?? null;

        if (!$orderId) {
            http_response_code(500);
            return [
                'error'   => true,
                'status'  => 500,
                'message' => 'Order ID missing from order data.'
            ];
        }

        // ✅ If total > 0, process payment
        if ($totalAmount > 0) {
            if ($data['payment_method'] === 'paypal') {
                $redirectUrl = Order::createPayPalPayment($orderId, $totalAmount);

                if ($redirectUrl) {
                    $order['redirect_url'] = $redirectUrl;
                } else {
                    $order['message'] .= ' (PayPal payment initialization failed)';
                    http_response_code(500);
                }
            }
        } else {
            // ✅ Auto-complete free orders
            R::exec('DELETE FROM carts WHERE user_id = ?', [$currentUser->user->id]);
            R::exec('UPDATE orders SET status = ? WHERE order_id = ?', ['completed', $orderId]);

            $order['message'] .= ' (No payment required for zero total amount)';
            $order['redirect_url'] = env('APP_URL') . '/checkout/success?order_id=' . urlencode($orderId);
        }

        return $order;
    }
}
