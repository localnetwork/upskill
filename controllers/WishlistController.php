<?php


require_once __DIR__ . '/../models/Wishlist.php';

class WishlistController
{
    public static function addToWishlist($userId, $courseId)
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $result = Wishlist::create($input);

        if (isset($result['error']) && $result['error']) {
            http_response_code($result['status']);
            echo json_encode(['errors' => $result['errors']]);
            return;
        }
        echo json_encode($result);
    }
}
