<?php
return [
    '_weight' => 10,
    'username' => 'VARCHAR(255) NOT NULL',
    'firstname' => 'VARCHAR(255) NOT NULL',
    'lastname' => 'VARCHAR(255) NOT NULL',
    'email'    => 'VARCHAR(255) NOT NULL',
    'password' => 'VARCHAR(255) NOT NULL',
    'verified' => 'BOOLEAN DEFAULT FALSE',
    'status'   => 'TINYINT(1) DEFAULT 1',
];
