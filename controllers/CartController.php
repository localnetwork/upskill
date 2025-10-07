<?php


require_once __DIR__ . '/../models/Cart.php';

class CartController
{
    public static function addToCart()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $result = Cart::addCart($input);

        if (isset($result['error']) && $result['error']) {
            http_response_code($result['status']);
            echo json_encode(['errors' => $result['errors']]);
            return;
        }
        echo json_encode($result);
    }

    public static function getCartItems()
    {
        $result = Cart::getCurrentUserCart();
        echo json_encode($result);
    }

    public static function getCartCount()
    {
        $result = Cart::getCartCount();
        echo json_encode($result);
    }

    public static function removeFromCart()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $result = Cart::removeFromCart($input);

        if (isset($result['error']) && $result['error']) {
            http_response_code($result['status']);
            echo json_encode(['errors' => $result['errors']]);
            return;
        }
        echo json_encode($result);
    }
}
