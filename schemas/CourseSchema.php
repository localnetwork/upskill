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
    'instructional_level' => 'INT(11) DEFAULT 1',
    'cover_image' => 'INT(255)',
    '_foreign' => [
        'fk_course_cover_image' => 'FOREIGN KEY (cover_image) REFERENCES medias(id) ON DELETE SET NULL',
        'fk_course_author' => 'FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE'
    ]
];
