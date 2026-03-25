<?php
// Auth Controller
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../lib/entities/UserHelper.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthController
{
    private static string $jwt_key;

    public static function init()
    {
        self::$jwt_key = env('JWT_SECRET');
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    public static function login()
    {
        $input = json_decode(file_get_contents('php://input'), true);

        try {
            User::login($input);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
        }
    }

    public static function register()
    {
        $input  = json_decode(file_get_contents('php://input'), true);
        $result = User::create($input);

        if (isset($result['error']) && $result['error']) {
            http_response_code($result['status']);
            echo json_encode(['errors' => $result['errors']]);
            return;
        }

        echo json_encode($result);
    }

    // ── 2FA ───────────────────────────────────────────────────────────────────

    public static function setup2FA()
    {
        try {
            User::setup2FA();
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
        }
    }

    public static function confirm2FA()
    {
        try {
            User::confirm2FA();
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
        }
    }

    public static function verify2FA()
    {
        try {
            header('Content-Type: application/json');

            $input = json_decode(file_get_contents('php://input'), true);

            // ✅ FIX: Call the method and get the result
            $result = User::verifyTwoFactorCode($input);

            // ✅ FIX: Extract http_code and set it
            $httpCode = $result['http_code'] ?? 500;
            http_response_code($httpCode);

            // ✅ FIX: Remove http_code from response and echo the result
            unset($result['http_code']);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    public static function redeemBackupCode()
    {
        try {
            header('Content-Type: application/json');

            $input = json_decode(file_get_contents('php://input'), true);

            $result   = User::redeemBackupCode($input);
            $httpCode = $result['http_code'] ?? 200;
            http_response_code($httpCode);

            unset($result['http_code']);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    public static function disable2FA()
    {
        try {
            User::disable2FA();
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
        }
    }



    public static function get2FAStatus()
    {
        try {
            User::get2FAStatus();
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
        }
    }

    // ── Token Helpers ─────────────────────────────────────────────────────────

    public static function getCurrentUser($returnNullIfNoToken = false)
    {
        $headers = getallheaders();

        if (!isset($headers['Authorization'])) {
            if ($returnNullIfNoToken) return null;
            http_response_code(401);
            echo json_encode(['error' => 'No token provided']);
            exit;
        }

        $token = str_replace('Bearer ', '', $headers['Authorization']);

        try {
            $decoded = JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256'));

            // Guard: token must have a user object with an id
            if (empty($decoded->user) || empty($decoded->user->id)) {
                if ($returnNullIfNoToken) return null;
                http_response_code(401);
                echo json_encode(['message' => 'Token payload invalid']);
                exit;
            }

            return $decoded;
        } catch (Exception $e) {
            if ($returnNullIfNoToken) return null;
            http_response_code(401);
            echo json_encode(['message' => 'Invalid or expired token: ' . $e->getMessage()]); // ← add message to see WHY
            exit;
        }
    }

    public static function verify($token)
    {
        try {
            return JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256'));
        } catch (\Exception $e) {
            return false;
        }
    }


    public static function regenerateBackupCodes()
    {
        try {
            header('Content-Type: application/json');

            $input = json_decode(file_get_contents('php://input'), true) ?? [];

            $result   = User::regenerateBackupCodes($input);
            $httpCode = $result['http_code'] ?? 200;

            http_response_code($httpCode);

            unset($result['http_code']);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ]);
        }
        exit;
    }
}

AuthController::init();
