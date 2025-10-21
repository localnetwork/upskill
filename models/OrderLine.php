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
        $currentUser = AuthController::getCurrentUser(true);
        if (!$currentUser) {
            return false;
        }

        $sql = '
        SELECT ol.*
        FROM order_lines ol
        JOIN orders o ON ol.order_id = o.id
        WHERE ol.course_id = ? 
          AND o.user_id = ? 
          AND o.status = ?
        LIMIT 1
    ';

        $orderLine = R::getRow($sql, [
            $courseId,
            $currentUser->user->id,
            'completed'
        ]);

        return !empty($orderLine);
    }
}
