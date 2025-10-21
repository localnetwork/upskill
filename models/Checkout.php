<?php

require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../config/env.php';

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

        $order = Order::createOrder(userId: $currentUser->user->id, paymentMethod: $data['payment_method']);

        if (!$order['error'] && $data['payment_method'] === 'paypal') {
            $redirectUrl = Order::createPayPalPayment($order['data']['order_id'], $order['data']['total_amount']);
            if ($redirectUrl) {
                $order['redirect_url'] = $redirectUrl;
            } else {
                $order['message'] .= ' (PayPal payment initialization failed)';
            }
        }

        return $order;
    }
}
