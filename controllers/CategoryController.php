<?php
require_once __DIR__ . '/../models/CourseCategory.php';

class CategoryController
{
    public static function getParentCategories()
    {
        $category = new Category();
        $categories = $category->getCategoriesHierarchy();

        header('Content-Type: application/json');
        echo json_encode($categories);
    }
}
