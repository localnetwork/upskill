<?php

return [
    'id'            => 'INT AUTO_INCREMENT PRIMARY KEY',
    'user_id'       => 'INT NOT NULL',
    'total_amount'  => 'DECIMAL(10,2) NOT NULL',
    'status'        => "ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending'",
    'order_id'      => 'VARCHAR(100) NOT NULL UNIQUE',
    '_foreign' => [
        'fk_order_user_id' => 'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
    ]
];
