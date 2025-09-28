<?php


class CourseSection
{
    public static function create($data) {}

    public static function getSectionsByCourseId($id)
    {
        return R::findAll('course_sections', 'course_id = ?', [$id]);
    }
}
