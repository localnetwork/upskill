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

        return self::capturePayPalPayment(paypalOrderId: $paypalOrderId);
    }

    public static function capturePayPalPayment($paypalOrderId)
    {
        // Call the model method to capture the payment
        return Order::capturePayPalPayment($paypalOrderId);
    }


    public static function show($orderId)
    {
        $response = Order::getOrderByOrderId($orderId);

        // Enforce the HTTP status code if it's included in the response
        if (isset($response['status'])) {
            http_response_code($response['status']);
        }

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function cancel($orderId)
    {
        $response = Order::cancelOrder($orderId);

        // Enforce the HTTP status code if it's included in the response
        if (isset($response['status'])) {
            http_response_code($response['status']);
        }

        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function learnings()
    {
        $response = Order::getLearning();

        // Enforce the HTTP status code if it's included in the response
        if (isset($response['status'])) {
            http_response_code($response['status']);
        }

        header('Content-Type: application/json');
        echo json_encode($response);
    }
}
