<?php


use RedBeanPHP\R;

function getUserRoles(int $user_id): array {
    return R::getAll(
        'SELECT r.*
         FROM user_roles ur
         INNER JOIN roles r ON ur.role_id = r.id
         WHERE ur.user_id = ?', 
        [$user_id]
    );
} 