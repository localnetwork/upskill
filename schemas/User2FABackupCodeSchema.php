<?php
return [
    '_weight' => 50,   // lower = earlier
    'user_id' => 'INT(255) NOT NULL',
    'code_hash' => 'VARCHAR(255) NOT NULL',
    'used_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',

    // Foreign keys
    '_foreign' => [
        'fk_user_2fa_backup_code_user' => 'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
    ]


];
