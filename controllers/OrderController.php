<?php


require_once __DIR__ . '/../models/Order.php';


class OrderController
{
    public static function paypalSuccess()
    {
        $paypalOrderId = $_GET['token'] ?? null;

        if (!$paypalOrderId) {
            return ['error' => true, 'message' => 'Missing PayPal order token.'];
        }

        return self::capturePayPalPayment($paypalOrderId);
    }

    public static function capturePayPalPayment($paypalOrderId)
    {
        // Call the model method to capture the payment
        return Order::capturePayPalPayment($paypalOrderId);
    }
}
