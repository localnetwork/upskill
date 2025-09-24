<?php
return [
    '_weight' => 25,
    'title' => 'VARCHAR(255) NOT NULL',
    'description' => 'TEXT',
    'published' => 'TINYINT(1) DEFAULT 1',
    'author_id' => 'INT(255) NOT NULL',
    'course_section_id' => 'INT(255) NOT NULL',
    'course_id' => 'INT(255) NOT NULL',
    '_foreign' => [
        'fk_lecture_course_author' => 'FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE',
        'fk_lecture_course_section' => 'FOREIGN KEY (course_section_id) REFERENCES course_sections(id) ON DELETE CASCADE',
        'fk_lecture_course' => 'FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE'
    ]
];
