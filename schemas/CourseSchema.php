<?php
return [
    '_weight' => 15,
    'title' => 'VARCHAR(255) NOT NULL',
    'overview' => 'TEXT',
    'published' => 'TINYINT(1) DEFAULT 0',
    'status'   => 'TINYINT(1) DEFAULT 1',
    'author_id' => 'INT(255) NOT NULL',
    '_foreign' => [
        'fk_course_author' => 'FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE'
    ]
];
