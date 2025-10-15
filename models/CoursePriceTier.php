<?php


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

use RedBeanPHP\R;

class CoursePriceTier
{
    public static function getAllTiers()
    {
        $tiers = R::findAll('course_price_tiers', 'ORDER BY price ASC');
        return R::exportAll($tiers);
    }

    public static function getCoursePriceTierById($id)
    {
        $tier = R::findOne('course_price_tiers', 'id = ?', [(int) $id]);
        return $tier ?: null;
    }
}
