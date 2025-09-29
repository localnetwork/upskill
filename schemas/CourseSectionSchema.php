<?php
return [
    '_weight' => 20,
    'title' => 'VARCHAR(255) NOT NULL',
    'section_description' => 'TEXT NOT NULL',
    'author_id' => 'INT(255) NOT NULL',
    'course_id' => 'INT(255) NOT NULL',
    '_foreign' => [
        'fk_section_course_author' => 'FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE',
        'fk_section_course_id' => 'FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE'
    ]
];
