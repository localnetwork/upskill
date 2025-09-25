<?php
return [
    '_weight' => 25,
    'title' => 'VARCHAR(255) NOT NULL',
    "path" => "VARCHAR(255) NOT NULL",
    "type" => "VARCHAR(100) NOT NULL",
    "size" => "INT(255) NOT NULL",
    "author_id" => "INT(255) NOT NULL",
    '_foreign' => [
        'fk_media_author' => 'FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE',
    ]
];
