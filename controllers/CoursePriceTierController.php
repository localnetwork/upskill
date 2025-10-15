<?php

require_once __DIR__ . '/../models/CoursePriceTier.php';
class CoursePriceTierController
{
    public static function get()
    {
        $tiers = CoursePriceTier::getAllTiers();
        echo json_encode($tiers);
    }
}
