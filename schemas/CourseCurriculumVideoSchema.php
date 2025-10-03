<?php
return [
    '_weight' => 10,
    'title' => 'VARCHAR(255) NOT NULL',
    'path' => 'VARCHAR(255) NOT NULL',
    'type' => 'VARCHAR(100) NOT NULL',
    'size' => 'INT(255) NOT NULL',
    'author_id' => 'INT(255) NOT NULL',
    'video_id' => 'INT(255) NOT NULL',
    'curriculum_id' => 'INT(255) NOT NULL',
    '_foreign' => [
        'fk_course_curriculum_author_id' => 'FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE',
        'fk_course_curriculum_video_id' => 'FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE',
        'fk_course_curriculum_id' => 'FOREIGN KEY (curriculum_id) REFERENCES course_curriculums(id) ON DELETE CASCADE',
    ]
];
