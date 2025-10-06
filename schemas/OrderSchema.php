<?php

return [
    'id'            => 'INT AUTO_INCREMENT PRIMARY KEY',
    'user_id'       => 'INT NOT NULL',
    'total_amount'  => 'DECIMAL(10,2) NOT NULL',
    'status'        => "ENUM('pending', 'completed', 'failed') DEFAULT 'pending'",
    'created_at'    => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
    '_foreign' => [
        'fk_order_user_id' => 'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
    ]
];
