<?php


function course_owner_middleware($courseId)
{
    // ✅ Normalize headers (case-insensitive)
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);

    if (!isset($headers['authorization'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No token provided']);
        exit;
    }

    // ✅ Remove "Bearer " prefix
    $token = trim(str_ireplace('Bearer', '', $headers['authorization']));
    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['error' => 'Empty token']);
        exit;
    }

    $jwt_secret = env('JWT_SECRET');

    try {
        // ✅ Verify JWT
        $decoded = AuthController::verify($token, $jwt_secret, 'HS256');
        if (!$decoded) {
            throw new Exception('Invalid token');
        }

        // ✅ Check if user has Educator role
        $roles = $decoded->user->roles ?? [];

        $hasEducator = false;
        foreach ($roles as $role) {
            // role may be array or object depending on how it's encoded
            $roleId   = is_object($role) ? ($role->id   ?? null) : ($role['id']   ?? null);
            $roleName = is_object($role) ? ($role->name ?? null) : ($role['name'] ?? null);

            if ($roleId == 2 || strcasecmp($roleName, 'Educator') === 0) {
                $hasEducator = true;
                break;
            }
        }

        if (!$hasEducator) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied. Educator role required.']);
            exit;
        }

        // ✅ Allow the request to continue 
        return $decoded;
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['message' => 'Invalid token.']);
        exit;
    }
}
