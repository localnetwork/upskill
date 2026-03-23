<?php

use RedBeanPHP\R;

class Category
{
    // Fetch all parent categories and their  
    public function getCategoriesHierarchy(): array
    {
        // Get all parent categories
        $parents = R::findAll('categories', 'parent_id IS NULL ORDER BY title ASC');

        $result = [];

        foreach ($parents as $parent) {
            // Fetch children of this parent
            $childrenBeans = R::findAll('categories', 'parent_id = ? ORDER BY title ASC', [$parent->id]);

            $children = [];
            foreach ($childrenBeans as $child) {
                $children[] = [
                    'id' => (int) $child->id,
                    'title' => $child->title,
                    'slug' => $child->slug,
                ];
            }

            $result[] = [
                'id' => (int) $parent->id,
                'title' => $parent->title,
                'slug' => $parent->slug,
                'children' => $children,
            ];
        }

        return $result;
    }

    public function updateCategory($id, $data)
    {
        $category = R::load('categories', $id);
        if (!$category->id) {
            throw new Exception("Category not found");
        }

        foreach ($data as $key => $value) {
            $category->$key = $value;
        }

        R::store($category);
    }

    public function createCategory($data)
    {
        $category = R::dispense('categories');

        foreach ($data as $key => $value) {
            $category->$key = $value;
        }

        R::store($category);
    }

    public function getCategoryBySlug($slug)
    {
        $category = R::findOne('categories', 'slug = ?', [$slug]);
        if (!$category) {
            throw new Exception("Category not found");
        }

        return $category;
    }

    public function deleteCategory($id)
    {
        $category = R::load('categories', $id);
        if (!$category->id) {
            throw new Exception("Category not found");
        }

        R::trash($category);
    }
}
