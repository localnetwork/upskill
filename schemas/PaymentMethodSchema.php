<?php

return [
    'id'        => 'INT AUTO_INCREMENT PRIMARY KEY',
    'name'      => 'VARCHAR(100) NOT NULL',
    'gateway'   => 'ENUM(\'paypal\') NOT NULL',
    'description' => 'TEXT',
    'instruction' => 'TEXT',
    'status' => 'BOOLEAN DEFAULT FALSE',
];
