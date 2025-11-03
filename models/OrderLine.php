<?php

require_once __DIR__ . '/../models/Course.php';
require_once __DIR__ . '/../controllers/AuthController.php';


use RedBeanPHP\R; // ✅ Import RedBeanPHP static facade  


class OrderLine
{
    public static function createOrderLine($orderId, $course_id, $price)
    {
        return [
            'id'        => 1,
            'order_id'  => $orderId,
            'course_id' => $course_id,
            'price'     => $price,
        ];
    }

    public static function getOrderLinesByOrderId($orderId)
    {
        $orderLines = R::findAll('order_lines', 'order_id = ?', [$orderId]);

        if (empty($orderLines)) {
            return [];
        }

        // ✅ Convert associative array to numeric array before mapping 
        $orderLines = array_values($orderLines);

        return array_map(function ($line) {
            return [
                'id'        => $line->id,
                'order_id'  => $line->order_id,
                'course'    => Course::viewCourseByID($line->course_id),
                'price'     => (float) $line->price,
            ];
        }, $orderLines);
    }

    public static function checkCourseEnrolled($courseId)
    {
        // ✅ Get current logged-in user
        $currentUser = AuthController::getCurrentUser(true);

        // ✅ Handle missing or invalid user
        if (!$currentUser) {
            return false;
        }

        // ✅ Normalize user ID (support both object or nested property)
        $userId = null;
        if (is_object($currentUser)) {
            $userId = $currentUser->user->id ?? $currentUser->id ?? null;
        } elseif (is_array($currentUser)) {
            $userId = $currentUser['user']['id'] ?? $currentUser['id'] ?? null;
        }

        if (!$userId) {
            return false;
        }

        // ✅ Use COUNT for efficient existence check
        $count = R::count('order_lines', '
        course_id = ? 
        AND order_id IN (
            SELECT id FROM orders 
            WHERE user_id = ? AND status = ?
        )
    ', [
            (int)$courseId,
            (int)$userId,
            'completed',
        ]);

        // ✅ Return true if at least one record found
        return $count > 0;
    }
}
