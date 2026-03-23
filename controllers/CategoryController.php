<?php
require_once __DIR__ . '/../models/Category.php';

class CategoryController
{
    public static function getParentCategories()
    {
        $category = new Category();
        $categories = $category->getCategoriesHierarchy();

        header('Content-Type: application/json');
        echo json_encode($categories);
    }

    public static function viewCategoryBySlug($slug)
    {
        if (!$slug) {
            http_response_code(400);
            echo json_encode(['error' => 'Slug is required']);
            return;
        }

        try {
            $category = new Category();
            $foundCategory = $category->getCategoryBySlug($slug); // instance call, not static

            header('Content-Type: application/json');
            echo json_encode($foundCategory->export()); // output the result
        } catch (Exception $e) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
