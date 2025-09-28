<?php
return [
    '_weight' => 15,
    'requirements_data' => 'TEXT NOT NULL',
    'what_you_will_learn_data' => 'TEXT NOT NULL',
    'who_should_attend_data' => 'TEXT NOT NULL',
    'course_id' => 'INT(11) NOT NULL',
    '_foreign' => [
        'fk_goal_course_id' => 'FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE'
    ],
];
