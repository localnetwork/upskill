<?php
return [
    '_weight' => 10,
    'title' => 'VARCHAR(255) NOT NULL',
    'path' => 'VARCHAR(255) NOT NULL',
    'type' => 'VARCHAR(100) NOT NULL',
    'size' => 'INT(255) NOT NULL',
    'author_id' => 'INT(255) NOT NULL',
    'video_blob' => 'LONGBLOB',
    'estimated_duration' => 'INT(11) DEFAULT NULL',
    '_foreign' => [
        'fk_video_author' => 'FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE',
    ]
];
