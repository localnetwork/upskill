<?php
return [
    '_weight' => 20,   // lower = earlier
    'role_id' => 'INT(255) NOT NULL',
    'user_id' => 'INT(255) NOT NULL',
    '_foreign' => [
        'fk_user_role_user' => 'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
        'fk_user_role_role' => 'FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE'
    ]
];
