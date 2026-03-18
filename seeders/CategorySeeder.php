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

    public function run(): void
    {
        $authorId = 1;

        if (!R::testConnection()) {
            echo "❌ Database connection failed. Make sure R::setup() was called before running this seeder.\n";
            return;
        }

        $mainCategories = [
            'Development',
            'Business',
            'Finance & Accounting',
            'IT & Software',
            'Office Productivity',
            'Personal Development',
            'Design',
            'Marketing',
            'Lifestyle',
            'Photography & Video',
            'Health & Fitness',
            'Music',
            'Teaching & Academics',
        ];

        $subcategories = [
            'Development'          => [
                'Web Development',
                'Data Science',
                'Mobile Development',
                'Programming Languages',
                'Game Development',
                'Database Design & Development',
                'Software Testing',
                'Software Engineering',
                'Software Development Tools',
                'No-Code Development',
            ],
            'Business'             => [
                'Entrepreneurship',
                'Communication',
                'Management',
                'Sales',
                'Business Strategy',
                'Operations',
                'Project Management',
                'Business Law',
                'Business Analytics & Intelligence',
                'Human Resources',
                'Industry',
                'E-Commerce',
                'Media',
                'Real Estate',
                'Other Business',
            ],
            'Finance & Accounting' => [
                'Accounting & Bookkeeping',
                'Compliance',
                'Cryptocurrency & Blockchain',
                'Economics',
                'Finance',
                'Finance Cert & Exam Prep',
                'Financial Modeling & Analysis',
                'Investing & Trading',
                'Money Management Tools',
                'Taxes',
                'Other Finance & Accounting',
            ],
            'IT & Software'        => [
                'IT Certifications',
                'Network & Security',
                'Hardware',
                'Operating Systems & Servers',
                'Other IT & Software',
            ],
            'Office Productivity'  => [
                'Microsoft',
                'Apple',
                'Google',
                'SAP',
                'Oracle',
                'Other Office Productivity',
            ],
            'Personal Development' => [
                'Personal Transformation',
                'Personal Productivity',
                'Leadership',
                'Career Development',
                'Parenting & Relationships',
                'Happiness',
                'Esoteric Practices',
                'Religion & Spirituality',
                'Personal Brand Building',
                'Creativity',
                'Influence',
                'Self Esteem & Confidence',
                'Stress Management',
                'Memory & Study Skills',
                'Motivation',
                'Other Personal Development',
            ],
            'Design'               => [
                'Web Design',
                'Graphic Design & Illustration',
                'Design Tools',
                'User Experience Design',
                'Game Design',
                '3D & Animation',
                'Fashion Design',
                'Architectural Design',
                'Interior Design',
                'Other Design',
            ],
            'Marketing'            => [
                'Digital Marketing',
                'Search Engine Optimization',
                'Social Media Marketing',
                'Branding',
                'Marketing Fundamentals',
                'Marketing Analytics & Automation',
                'Public Relations',
                'Paid Advertising',
                'Video & Mobile Marketing',
                'Content Marketing',
                'Growth Hacking',
                'Affiliate Marketing',
                'Product Marketing',
                'Other Marketing',
            ],
            'Lifestyle'            => [
                'Arts & Crafts',
                'Beauty & Makeup',
                'Esoteric Practices',
                'Food & Beverage',
                'Gaming',
                'Home Improvement & Gardening',
                'Pet Care & Training',
                'Travel',
                'Other Lifestyle',
            ],
            'Photography & Video'  => [
                'Digital Photography',
                'Photography',
                'Portrait Photography',
                'Photography Tools',
                'Commercial Photography',
                'Video Design',
                'Other Photography & Video',
            ],
            'Health & Fitness'     => [
                'Fitness',
                'General Health',
                'Sports',
                'Nutrition & Diet',
                'Yoga',
                'Mental Health',
                'Martial Arts & Self Defense',
                'Safety & First Aid',
                'Dance',
                'Meditation',
                'Other Health & Fitness',
            ],
            'Music'                => [
                'Instruments',
                'Music Production',
                'Music Fundamentals',
                'Vocal',
                'Music Techniques',
                'Music Software',
                'Other Music',
            ],
            'Teaching & Academics' => [
                'Engineering',
                'Humanities',
                'Math',
                'Science',
                'Online Education',
                'Social Science',
                'Language Learning',
                'Teacher Training',
                'Test Prep',
                'Other Teaching & Academics',
            ],
        ];

        $categoryMap = [];

        echo "🚀 Starting CourseCategorySeeder...\n";

        R::begin();

        try {
            // ── Main categories ───────────────────────────────────────────
            foreach ($mainCategories as $title) {
                $existing = R::findOne('categories', 'title = ? AND parent_id IS NULL', [$title]);

                if ($existing) {
                    $categoryMap[$title] = (int) $existing->id;
                    echo "⚠️  Parent category exists: {$title} (ID: {$existing->id})\n";
                    continue;
                }

                $uuid = Uuid::uuid4()->toString();
                $slug = $this->uniqueSlug($title);
                $now  = R::isoDateTime();

                R::exec(
                    "INSERT INTO categories (uuid, title, slug, parent_id, author_id, created_at, updated_at)
                     VALUES (?, ?, ?, NULL, ?, ?, ?)",
                    [$uuid, $title, $slug, $authorId, $now, $now]
                );

                // Fetch the inserted row by UUID to get the real ID
                $inserted = R::findOne('categories', 'uuid = ?', [$uuid]);

                if (!$inserted) {
                    throw new \RuntimeException("Could not find inserted parent category: {$title}");
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
                    $now  = R::isoDateTime();

                    R::exec(
                        "INSERT INTO categories (uuid, title, slug, parent_id, author_id, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?)",
                        [$uuid, $title, $slug, $parentId, $authorId, $now, $now]
                    );

                    // Fetch the inserted row by UUID to get the real ID
                    $inserted = R::findOne('categories', 'uuid = ?', [$uuid]);

                    if (!$inserted) {
                        throw new \RuntimeException("Could not find inserted subcategory: {$title} (Parent: {$parentTitle})");
                    }

                    echo "✅ Inserted subcategory: {$title} (Parent: {$parentTitle}, ID: {$inserted->id}, slug: {$slug})\n";
                }
            }

            R::commit();
            echo "\n✅ CourseCategorySeeder completed successfully.\n";
        } catch (\Throwable $e) {
            R::rollback();
            echo "\n❌ Seeder failed and was rolled back.\n";
            echo "   Error: " . $e->getMessage() . "\n";
            echo "   File:  " . $e->getFile() . " (line " . $e->getLine() . ")\n";
        }
    }
}
