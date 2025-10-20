<?php

return [
    'id'          => 'INT AUTO_INCREMENT PRIMARY KEY',
    'order_id'    => 'INT NOT NULL',
    'course_id'   => 'INT NOT NULL',
    'price'       => 'DECIMAL(10,2) NOT NULL',
    '_foreign' => [
        'fk_orderline_order_id'  => 'FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE',
        'fk_orderline_course_id' => 'FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE',
    ]
];
