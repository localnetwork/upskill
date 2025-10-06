<?php

return [
    'id'            => 'INT AUTO_INCREMENT PRIMARY KEY',
    'user_id'       => 'INT NOT NULL',
    'course_id'     => 'INT NOT NULL',
    'created_at'    => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
    '_foreign' => [
        'fk_wishlist_user_id' => 'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
        'fk_wishlist_course_id' => 'FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE',
    ]
];
