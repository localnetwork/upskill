<?php
return [
    '_weight' => 10,
    'username' => 'VARCHAR(255) NOT NULL',
    'firstname' => 'VARCHAR(255) NOT NULL',
    'lastname' => 'VARCHAR(255) NOT NULL',
    'headline' => 'VARCHAR(100) DEFAULT NULL',
    'biography' => 'TEXT DEFAULT NULL',
    'user_picture' => 'INT(255) DEFAULT NULL',
    'link_website' => 'VARCHAR(255) DEFAULT NULL',
    'link_x' => 'VARCHAR(255) DEFAULT NULL',
    'link_linkedin' => 'VARCHAR(255) DEFAULT NULL',
    'link_instagram' => 'VARCHAR(255) DEFAULT NULL',
    'link_facebook' => 'VARCHAR(255) DEFAULT NULL',
    'link_tiktok' => 'VARCHAR(255) DEFAULT NULL',
    'link_youtube' => 'VARCHAR(255) DEFAULT NULL',
    'link_github' => 'VARCHAR(255) DEFAULT NULL',
    'email'    => 'VARCHAR(255) NOT NULL',
    'password' => 'VARCHAR(255) NOT NULL',
    'verified' => 'BOOLEAN DEFAULT FALSE',
    'status'   => 'TINYINT(1) DEFAULT 1',
    '_foreign' => [
        'fk_user_picture' => 'FOREIGN KEY (user_picture) REFERENCES medias(id) ON DELETE CASCADE',
    ]
];
