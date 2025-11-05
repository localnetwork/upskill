<?php
return [
    '_isPlural' => false,
    'author_id'    => 'INT NOT NULL',
    'curriculum_id'   => 'INT NOT NULL',
    '_foreign' => [
        'fk_curriculump_author' => 'FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE',
        'fk_curriculump_lecture' => 'FOREIGN KEY (curriculum_id) REFERENCES course_curriculums(id) ON DELETE CASCADE',
    ]
];
