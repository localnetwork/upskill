<?php

use RedBeanPHP\R;
use Ramsey\Uuid\Uuid;

class CategorySeeder
{
    public static $weight = 120;

    private function slugify(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        return trim($text, '-');
    }

    private function uniqueSlug(string $title, ?string $parentSlug = null): string
    {
        $base = $this->slugify($title);

        $exists = R::count('categories', 'slug = ?', [$base]);

        if ($exists === 0) {
            return $base;
        }

        $fallback = $parentSlug
            ? $base . '-' . $this->slugify($parentSlug)
            : $base . '-' . substr(md5(uniqid()), 0, 6);

        $stillExists = R::count('categories', 'slug = ?', [$fallback]);

        return $stillExists === 0
            ? $fallback
            : $base . '-' . substr(md5(uniqid()), 0, 6);
    }

    private function loadCategories(): array
    {
        $jsonPath = __DIR__ . '/../data/categories.json';

        if (!file_exists($jsonPath)) {
            throw new \RuntimeException("categories.json not found at: {$jsonPath}");
        }

        $json = file_get_contents($jsonPath);

        if ($json === false) {
            throw new \RuntimeException("Failed to read categories.json");
        }

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON in categories.json: " . json_last_error_msg());
        }

        return $data;
    }

    public function run(): void
    {
        $authorId = 1;

        if (!R::testConnection()) {
            echo "❌ Database connection failed. Make sure R::setup() was called before running this seeder.\n";
            return;
        }

        // Guard: confirm author exists before inserting anything
        $authorExists = R::count('users', 'id = ?', [$authorId]);
        if (!$authorExists) {
            echo "❌ author_id={$authorId} does not exist in the users table. Aborting.\n";
            return;
        }

        // Load categories from JSON and split into the same structure as before
        try {
            $data = $this->loadCategories();
        } catch (\RuntimeException $e) {
            echo "❌ " . $e->getMessage() . "\n";
            return;
        }

        $mainCategories = array_column($data, 'title');

        $subcategories = [];
        foreach ($data as $entry) {
            $subcategories[$entry['title']] = $entry['subcategories'] ?? [];
        }

        $categoryMap = [];

        echo "🚀 Starting CategorySeeder...\n";

        R::begin();

        try {
            // ── Main categories ───────────────────────────────────────────
            foreach ($mainCategories as $title) {

                // Find-or-create: reuse existing row if present
                $existing = R::findOne('categories', 'title = ? AND parent_id IS NULL', [$title]);

                if ($existing) {
                    $categoryMap[$title] = (int) $existing->id;
                    echo "⚠️  Parent category exists, reusing: {$title} (ID: {$existing->id})\n";
                    continue;
                }

                $uuid = Uuid::uuid4()->toString();
                $slug = $this->uniqueSlug($title);
                $now  = date('Y-m-d H:i:s');

                R::exec(
                    "INSERT INTO categories (uuid, title, slug, parent_id, author_id, created_at, updated_at)
                     VALUES (?, ?, ?, NULL, ?, ?, ?)",
                    [$uuid, $title, $slug, $authorId, $now, $now]
                );

                $inserted = R::findOne('categories', 'uuid = ?', [$uuid]);

                if (!$inserted) {
                    throw new \RuntimeException("Insert failed for parent category: {$title}");
                }

                $categoryMap[$title] = (int) $inserted->id;
                echo "✅ Inserted parent category: {$title} (ID: {$inserted->id}, slug: {$slug})\n";
            }

            // ── Subcategories ─────────────────────────────────────────────
            foreach ($subcategories as $parentTitle => $subs) {
                if (!isset($categoryMap[$parentTitle])) {
                    echo "❌ Parent category missing in map: {$parentTitle}\n";
                    continue;
                }

                $parentId = $categoryMap[$parentTitle];

                foreach ($subs as $title) {
                    $existing = R::findOne(
                        'categories',
                        'title = ? AND parent_id = ?',
                        [$title, $parentId]
                    );

                    if ($existing) {
                        echo "⚠️  Subcategory exists: {$title} (Parent: {$parentTitle})\n";
                        continue;
                    }

                    $uuid = Uuid::uuid4()->toString();
                    $slug = $this->uniqueSlug($title, $parentTitle);
                    $now  = date('Y-m-d H:i:s');

                    R::exec(
                        "INSERT INTO categories (uuid, title, slug, parent_id, author_id, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?)",
                        [$uuid, $title, $slug, $parentId, $authorId, $now, $now]
                    );

                    $inserted = R::findOne('categories', 'uuid = ?', [$uuid]);

                    if (!$inserted) {
                        throw new \RuntimeException("Insert failed for subcategory: {$title} (Parent: {$parentTitle})");
                    }

                    echo "✅ Inserted subcategory: {$title} (Parent: {$parentTitle}, ID: {$inserted->id}, slug: {$slug})\n";
                }
            }

            R::commit();
            echo "\n✅ CategorySeeder completed successfully.\n";
        } catch (\Throwable $e) {
            R::rollback();
            echo "\n❌ Seeder failed and was rolled back.\n";
            echo "   Error: " . $e->getMessage() . "\n";
            echo "   File:  " . $e->getFile() . " (line " . $e->getLine() . ")\n";
        }
    }
}
