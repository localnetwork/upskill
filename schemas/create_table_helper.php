<?php
// create_table_helper.php - Helper to create tables from schema arrays

function createTableFromSchema($pdo, $table, $schema) {
    $fields = [];
    foreach ($schema as $name => $type) {
        $fields[] = "`$name` $type";
    }
    $fields[] = "id INT AUTO_INCREMENT PRIMARY KEY";
    $sql = "CREATE TABLE IF NOT EXISTS `$table` (" . implode(', ', $fields) . ")";
    $pdo->exec($sql);
}
  