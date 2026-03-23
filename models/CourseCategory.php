<?php

use Ramsey\Uuid\Uuid;
use RedBeanPHP\R;

class CourseCategory
{
    private function deleteCourseCategories($courseId, $categoryIds)
    {
        $placeholders = implode(', ', array_fill(0, count($categoryIds), '?'));
        R::exec(
            "DELETE FROM course_categories WHERE course_id = ? AND category_id IN ($placeholders)",
            array_merge([$courseId], $categoryIds)
        );
    }

    private function addCourseCategories($courseId, $categoryIds, $authorId)
    {
        $now = R::isoDateTime();
        $placeholders = implode(', ', array_fill(0, count($categoryIds), '(?, ?, ?, ?, ?, ?)'));
        $params = [];

        foreach ($categoryIds as $categoryId) {
            array_push(
                $params,
                Uuid::uuid4()->toString(),
                $courseId,
                $categoryId,
                $authorId,
                $now,
                $now
            );
        }

        R::exec(
            "INSERT INTO course_categories (uuid, course_id, category_id, author_id, created_at, updated_at) 
             VALUES $placeholders",
            $params
        );
    }

    private function getExistingCategoryIds($courseId)
    {
        $rows = R::getAll(
            'SELECT category_id FROM course_categories WHERE course_id = ?',
            [$courseId]
        );

        return array_column($rows, 'category_id');
    }


    public function updateCourseCategories($courseId, $categoryIds, $authorId)
    {
        R::begin();
        try {
            $existingIds = $this->getExistingCategoryIds($courseId);

            $toDelete = array_diff($existingIds, $categoryIds);
            $toInsert = array_diff($categoryIds, $existingIds);

            if (!empty($toDelete)) {
                $this->deleteCourseCategories($courseId, array_values($toDelete));
            }

            if (!empty($toInsert)) {
                $this->addCourseCategories($courseId, array_values($toInsert), $authorId);
            }

            R::commit();
        } catch (\Exception $e) {
            R::rollback();
            throw $e;
        }

        return $categoryIds;
    }

    public static function getCourseCategories($courseId)
    {
        $rows = R::getAll(
            'SELECT cc.*, c.title, c.slug, c.parent_id 
         FROM course_categories cc
         JOIN categories c ON c.id = cc.category_id
         WHERE cc.course_id = ?',
            [$courseId]
        );

        return $rows;
    }
}
