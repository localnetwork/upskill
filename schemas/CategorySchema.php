<?php
return [
    '_weight' => 100,
    'title' => 'TEXT NOT NULL',
    'author_id' => 'INT(255) NOT NULL',
    'created_at' => 'DATETIME NOT NULL',
    'updated_at' => 'DATETIME NOT NULL',
    'category_image' => 'INT(255) NULL',
    'category_cover' => 'INT(255) NULL',
    'category_description' => 'TEXT NULL',
    'slug' => 'VARCHAR(255) NOT NULL UNIQUE',
    'parent_id' => 'INT(255) NULL',
    '_foreign' => [
        'fk_category_author_id' => 'FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE',
        'fk_category_image' => 'FOREIGN KEY (category_image) REFERENCES media(id) ON DELETE SET NULL',
        'fk_category_cover' => 'FOREIGN KEY (category_cover) REFERENCES media(id) ON DELETE SET NULL',
        'fk_category_parent_id' => 'FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL',
    ]
];
