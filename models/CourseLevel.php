<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

use RedBeanPHP\R; // ✅ Import RedBeanPHP static facade 

class CourseLevel
{
    public static function getCourseLevelById($id)
    {
        return R::findOne('course_levels', 'id = ?', [$id]);
    }
}
