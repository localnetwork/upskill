<?php
return [
    '_weight' => 25,
    'content' => 'TEXT NOT NULL',
    'author_id' => 'INT(255) NOT NULL',
    'curriculum_id' => 'INT(255) NOT NULL',
    '_foreign' => [
        'fk_course_article_author_id' => 'FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE',
        'fk_course_article_id' => 'FOREIGN KEY (curriculum_id) REFERENCES course_curriculums(id) ON DELETE CASCADE',
    ]
];
