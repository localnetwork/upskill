<?php
return [
    '_weight' => 15,
    'title' => 'VARCHAR(255) NOT NULL',
    'subtitle' => 'VARCHAR(255)',
    'description' => 'TEXT',
    'published' => 'TINYINT(1) DEFAULT 0',
    'status'   => 'TINYINT(1) DEFAULT 1',
    'author_id' => 'INT(255) NOT NULL',
    'slug' => 'VARCHAR(255) NOT NULL UNIQUE',
    '_foreign' => [
        'fk_course_author' => 'FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE'
    ]
];
