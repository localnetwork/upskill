<?php
// ✅ Load RedBeanPHP
require_once __DIR__ . '/../vendor/autoload.php'; // adjust if needed
require_once __DIR__ . '/../config/database.php'; // where R::setup() is called

use RedBeanPHP\R;
use Ramsey\Uuid\Uuid;

class UserRole
{
    /**
     * Insert a user role record into the user_roles table.
     *
     * @param int $userId 
     * @param int $roleId
     * @return int  Inserted bean ID
     */
    public static function create($userId, $roleId)
    {
        $uuid = Uuid::uuid4()->toString();

        R::exec(
            "INSERT INTO user_roles (uuid, user_id, role_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?)",
            [$uuid, $userId, $roleId, R::isoDateTime(), R::isoDateTime()]
        );

        return R::getInsertID(); // ✅ returns the last inserted ID   
    }
    /** 
     * Get all roles for a given user.
     *
     * @param int $userId 
     * @return array
     */
    public static function getUserRoles($userId)
    {
        $sql = "
        SELECT ur.*, r.name AS role_name 
        FROM user_roles ur 
        INNER JOIN roles r ON ur.role_id = r.id  
        WHERE ur.user_id = ?
    ";

        $rows = R::getAll($sql, [(int) $userId]);
        return array_map(fn($row) => (object) $row, $rows);
    }
}
