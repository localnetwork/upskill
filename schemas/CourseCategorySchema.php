<?php
return [
    '_weight' => 25,
    'author_id' => 'INT(255) NOT NULL',
    'course_id' => 'INT(255) NOT NULL',
    'category_id' => 'INT(255) NOT NULL',
    '_foreign' => [
        'fk_course_category_author_id' => 'FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE',
        'fk_course_category_course_id' => 'FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE',
        'fk_course_category_category_id' => 'FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE',
    ]
];
