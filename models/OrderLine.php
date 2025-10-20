<?php

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
}
