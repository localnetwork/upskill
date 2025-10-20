<?php

/**
 * Synchronize a table with the given schema.
 *
 * - Creates the table if it does not exist. 
 * - Adds missing columns.
 * - Drops columns not present in the schema (except `id`, `uuid`, `created_at`, `updated_at`).
 * - Warns if a column type differs (does NOT auto-change for safety).
 * - Ensures `id` is always the first column.
 * - Ensures `uuid`, `created_at`, `updated_at` exist.
 * - Adds indexes and foreign keys safely.
 */
function createTableFromSchema(PDO $pdo, string $table, array $schema): void
{
    // Separate meta keys
    $columns  = [];
    $indexes  = $schema['_indexes'] ?? [];
    $foreigns = $schema['_foreign'] ?? [];

    foreach ($schema as $name => $type) {
        if (in_array($name, ['_weight', '_indexes', '_foreign'], true)) continue;
        $columns[$name] = $type;
    }

    // Ensure `id` is first
    if (!isset($columns['id'])) {
        $columns = ['id' => 'INT AUTO_INCREMENT PRIMARY KEY'] + $columns;
    }

    // Ensure `uuid` column exists
    if (!isset($columns['uuid'])) {
        $columns['uuid'] = 'VARCHAR(255) NOT NULL';
    }

    // Ensure created_at and updated_at exist
    if (!isset($columns['created_at'])) {
        $columns['created_at'] = 'DATETIME DEFAULT CURRENT_TIMESTAMP';
    }
    if (!isset($columns['updated_at'])) {
        $columns['updated_at'] = 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
    }

    // Check if table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    $exists = $stmt->fetchColumn();

    if (!$exists) {
        // Create table
        $colDefs = [];
        foreach ($columns as $name => $type) {
            $colDefs[] = "`$name` $type";
        }
        $sql = "CREATE TABLE `$table` (" . implode(', ', $colDefs) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $pdo->exec($sql);
        echo "➕ Created table: {$table}\n";
    } else {
        // Sync columns
        $existingCols = [];
        $rows = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $col) {
            $existingCols[$col['Field']] = strtolower($col['Type']);
        }

        // Add missing columns
        foreach ($columns as $name => $type) {
            if (!isset($existingCols[$name])) {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$name` $type");
                echo "➕ Added column `$name` to {$table}\n";
            }
        }

        // Drop columns not in schema (except id, uuid, created_at, updated_at)
        foreach ($existingCols as $name => $_) {
            if (!in_array($name, ['id', 'uuid', 'created_at', 'updated_at']) && !isset($columns[$name])) {
                $pdo->exec("ALTER TABLE `$table` DROP COLUMN `$name`");
                echo "➖ Dropped column `$name` from {$table}\n";
            }
        }
    }

    // Ensure uuid has unique index
    $uuidIndexCheck = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = ? 
        AND COLUMN_NAME = 'uuid'
        AND NON_UNIQUE = 1
    ");
    $uuidIndexCheck->execute([$table]);
    if ($uuidIndexCheck->fetchColumn()) {
        $pdo->exec("CREATE UNIQUE INDEX idx_{$table}_uuid ON `$table`(uuid)");
        echo "➕ Added unique index on uuid for {$table}\n";
    }

    // Add indexes and foreign keys (same as before)
    foreach ($indexes as $name => $def) {
        $def = trim($def);
        if ($def === '') continue;

        $check = $pdo->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND INDEX_NAME = ?
        ");
        $check->execute([$table, $name]);
        $exists = $check->fetchColumn();

        if (!$exists) {
            $pdo->exec("ALTER TABLE `$table` ADD $def");
            echo "➕ Added index $name on {$table}\n";
        } else {
            echo "⚠️ Skipped existing index $name on {$table}\n";
        }
    }

    foreach ($foreigns as $name => $def) {
        $def = trim($def);
        if ($def === '') continue;

        if (!preg_match('/FOREIGN KEY\s*\((.*?)\)\s*REFERENCES\s*(\w+)\((.*?)\)/i', $def, $matches)) {
            echo "⚠️ Invalid foreign key definition `$name` on {$table}, skipping.\n";
            continue;
        }

        $fkCols   = trim($matches[1]);
        $refTable = trim($matches[2]);
        $refCols  = trim($matches[3]);

        $stmtCheck = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmtCheck->execute([$refTable]);
        if (!$stmtCheck->fetchColumn()) {
            echo "⚠️ Referenced table `$refTable` does not exist, skipping foreign key `$name` on {$table}\n";
            continue;
        }

        $check = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ? 
              AND CONSTRAINT_NAME = ?
        ");
        $check->execute([$table, $name]);
        $exists = $check->fetchColumn();

        if (!$exists) {
            try {
                $pdo->exec("ALTER TABLE `$table` ADD CONSTRAINT `$name` $def");
                echo "➕ Added foreign key $name on {$table}\n";
            } catch (PDOException $e) {
                echo "❌ Failed to add foreign key $name on {$table}: " . $e->getMessage() . "\n";
            }
        } else {
            echo "⚠️ Skipped existing foreign key $name on {$table}\n";
        }
    }
}


function orderIdGenerator($length = 10)
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}
