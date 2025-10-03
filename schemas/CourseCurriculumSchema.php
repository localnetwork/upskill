<?php
return [
    '_weight' => 25,
    'title' => 'VARCHAR(255) NOT NULL',
    'curriculum_description' => 'TEXT',
    'published' => 'TINYINT(1) DEFAULT 0',
    'author_id' => 'INT(255) NOT NULL',
    'course_section_id' => 'INT(255) NOT NULL',
    'curriculum_type' => 'VARCHAR(100) NOT NULL',
    'sort_order' => 'INT(11) DEFAULT 0',
    'curriculum_resource_type' => 'VARCHAR(100)',
    '_foreign' => [
        'fk_curriculum_course_author' => 'FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE',
        'fk_curriculum_course_section' => 'FOREIGN KEY (course_section_id) REFERENCES course_sections(id) ON DELETE CASCADE',
    ]
];
