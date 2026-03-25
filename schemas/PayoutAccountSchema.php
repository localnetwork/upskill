<?php
return [
    '_weight' => 30,
    'user_id'    => 'INT NOT NULL',
    'provider'  => 'ENUM("paypal", "stripe") NOT NULL',
    'provider_account_id' => 'VARCHAR(255) NOT NULL',
    'provider_email' => 'VARCHAR(255) NOT NULL',
    'is_default' => 'BOOLEAN NOT NULL DEFAULT FALSE',
    'status' => 'ENUM("active", "inactive") NOT NULL DEFAULT "active"',
    // Foreign keys
    '_foreign' => [
        'fk_user_payout_account_user' => 'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
    ]
];
