<?php
return [
    '_weight' => 20,
    'title' => 'VARCHAR(255) NOT NULL',
    'author_id' => 'INT(255) NOT NULL',
    '_foreign' => [
        'fk_tag_course_author' => 'FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE',
    ]
];
