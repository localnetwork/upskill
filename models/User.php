<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

require_once __DIR__ . '/../models/UserRole.php';

use Ramsey\Uuid\Uuid;
use Firebase\JWT\JWT;
use RedBeanPHP\R;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class User
{
    protected static string $jwt_key;

    protected static function jwtKey(): string
    {
        if (!isset(self::$jwt_key)) {
            self::$jwt_key = env('JWT_SECRET', 'default_secret');
        }
        return self::$jwt_key;
    }

    // =========================================================================
    // EXISTING METHODS (unchanged)
    // =========================================================================

    public static function create($data)
    {
        $validator  = new \Rakit\Validation\Validator;
        $validation = $validator->make($data, [
            'username'         => 'required|max:10|min:3|regex:/^[A-Za-z0-9@_-]+$/',
            'firstname'        => 'required|max:30|min:2',
            'lastname'         => 'required|max:30|min:2',
            'password'         => 'required|max:16|min:8|regex:/^(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z0-9]).+$/',
            'confirm_password' => 'required|max:16|same:password',
            'email'            => 'required|max:60|regex:/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/',
            'role'             => 'required|in:2,3',
        ], [
            'username:regex'        => 'Username can only contain letters, numbers, dashes (-), underscores (_), or @.',
            'firstname:min'         => 'Firstname must be at least 2 characters long.',
            'lastname:min'          => 'Lastname must be at least 2 characters long.',
            'password:regex'        => 'Password must contain at least one letter, one number, and one special character.',
            'confirm_password:same' => 'Confirm password must match the password.',
            'email:regex'           => 'Please enter a valid email address.',
            'role:in'               => 'Role must be either Teacher (2) or Student (3).',
        ]);
        $validation->validate();

        if (!$validation->fails()) {
            if (R::findOne('users', 'username = ?', [$data['username']])) {
                $validation->errors()->add('username', 'custom', 'The username is already taken.');
            }
            if (R::findOne('users', 'email = ?', [$data['email']])) {
                $validation->errors()->add('email', 'custom', 'The email is already registered.');
            }
        }

        if ($validation->fails()) {
            return [
                'error'   => true,
                'status'  => 422,
                'errors'  => $validation->errors()->firstOfAll(),
                'message' => 'Please check the validated fields.'
            ];
        }

        R::begin();
        try {
            $user            = R::dispense('users');
            $user->uuid      = Uuid::uuid4()->toString();
            $user->username  = $data['username'];
            $user->password  = password_hash($data['password'], PASSWORD_DEFAULT);
            $user->email     = $data['email'];
            $user->firstname = $data['firstname'];
            $user->lastname  = $data['lastname'];
            $user->verified  = 0;
            $user->status    = 1;

            $userId     = R::store($user);
            $roleId     = (int) $data['role'];
            $userRoleId = UserRole::create($userId, $roleId);

            if (!$userRoleId) {
                throw new \Exception('Failed to create user role record.');
            }

            R::commit();
        } catch (\Exception $e) {
            R::rollback();
            return [
                'error'  => true,
                'status' => 500,
                'errors' => ['general' => 'Transaction failed: ' . $e->getMessage()]
            ];
        }

        $roles   = UserRole::getUserRoles($userId);
        $payload = [
            'user' => [
                'id'        => $user->id,
                'username'  => $user->username,
                'firstname' => $user->firstname,
                'lastname'  => $user->lastname,
                'email'     => $user->email,
                'uuid'      => $user->uuid,
                'verified'  => $user->verified,
                'status'    => $user->status,
                'roles'     => $roles,
            ],
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        $jwt = JWT::encode($payload, env('JWT_SECRET'), 'HS256');

        echo json_encode([
            'message' => 'User registered successfully.',
            'token'   => $jwt,
            'user'    => [
                'id'           => $user->id,
                'username'     => $user->username,
                'firstname'    => $user->firstname,
                'lastname'     => $user->lastname,
                'email'        => $user->email,
                'uuid'         => $user->uuid,
                'roles'        => $roles,
                'verified'     => $user->verified,
                'status'       => $user->status,
                'biography'    => $user->biography,
                'headline'     => $user->headline,
                'link_website' => $user->link_website,
                'link_x'       => $user->link_x,
                'link_linkedin'  => $user->link_linkedin,
                'link_instagram' => $user->link_instagram,
                'link_facebook'  => $user->link_facebook,
                'link_tiktok'    => $user->link_tiktok,
                'link_youtube'   => $user->link_youtube,
                'link_github'    => $user->link_github,
            ]
        ]);
        exit;
    }

    public static function login(array $data)
    {
        header('Content-Type: application/json');

        $validator  = new \Rakit\Validation\Validator;
        $validation = $validator->make($data, [
            'username' => 'required',
            'password' => 'required',
        ]);
        $validation->validate();

        if ($validation->fails()) {
            http_response_code(422);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Please check the validated fields.',
                'errors'  => $validation->errors()->firstOfAll()
            ]);
            exit;
        }

        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        $user = self::findByUsername($username) ?? self::findByEmail($username);

        if (!$user || !password_verify($password, $user->password)) {
            http_response_code(422);
            echo json_encode(['message' => 'These credentials do not match our records.']);
            exit;
        }

        $roles = UserRole::getUserRoles($user->id);

        // ── 2FA check ──────────────────────────────────────────────────────────
        if ($user->totp_enabled) {
            // Issue a short-lived pre-auth token (5 min), not a full JWT
            $preAuthPayload = [
                'sub'  => $user->id,
                'type' => 'pre_auth',
                'iat'  => time(),
                'exp'  => time() + 300, // 5 minutes
            ];
            $preAuthToken = JWT::encode($preAuthPayload, env('JWT_SECRET'), 'HS256');

            echo json_encode([
                'status'         => 'requires_2fa',
                'requires_2fa'   => true,
                'pre_auth_token' => $preAuthToken,
            ]);
            exit;
        }
        // ── end 2FA check ──────────────────────────────────────────────────────

        $payload = [
            'user' => [
                'id'        => $user->id,
                'username'  => $user->username,
                'firstname' => $user->firstname,
                'lastname'  => $user->lastname,
                'email'     => $user->email,
                'uuid'      => $user->uuid,
                'verified'  => $user->verified,
                'status'    => $user->status,
                'roles'     => $roles,
            ],
            'iat' => time(),
        ];

        $jwt = JWT::encode($payload, env('JWT_SECRET'), 'HS256');

        echo json_encode([
            'status' => 'success',
            'token'  => $jwt,
            'user'   => [
                'id'           => $user->id,
                'username'     => $user->username,
                'firstname'    => $user->firstname,
                'lastname'     => $user->lastname,
                'email'        => $user->email,
                'uuid'         => $user->uuid,
                'verified'     => $user->verified,
                'status'       => $user->status,
                'roles'        => $roles,
                'biography'    => $user->biography,
                'headline'     => $user->headline,
                'user_picture' => Media::getMediaById($user->user_picture),
                'link_website' => $user->link_website,
                'link_x'       => $user->link_x,
                'link_linkedin'  => $user->link_linkedin,
                'link_instagram' => $user->link_instagram,
                'link_facebook'  => $user->link_facebook,
                'link_tiktok'    => $user->link_tiktok,
                'link_youtube'   => $user->link_youtube,
                'link_github'    => $user->link_github,
            ]
        ]);
        exit;
    }

    public static function getPublicProfile($username)
    {
        $user = self::findByUsername($username);
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        $roles = UserRole::getUserRoles($user->id);

        echo json_encode([
            'id'           => $user->id,
            'username'     => $user->username,
            'firstname'    => $user->firstname,
            'lastname'     => $user->lastname,
            'uuid'         => $user->uuid,
            'roles'        => $roles,
            'biography'    => $user->biography,
            'headline'     => $user->headline,
            'user_picture' => Media::getMediaById($user->user_picture),
            'link_website' => $user->link_website,
            'link_x'       => $user->link_x,
            'link_linkedin'  => $user->link_linkedin,
            'link_instagram' => $user->link_instagram,
            'link_facebook'  => $user->link_facebook,
            'link_tiktok'    => $user->link_tiktok,
            'link_youtube'   => $user->link_youtube,
            'link_github'    => $user->link_github,
        ]);
        exit;
    }

    public static function findByUsername($username)
    {
        return R::findOne('users', 'username = ?', [$username]);
    }

    public static function findByEmail($email)
    {
        return R::findOne('users', 'email = ?', [$email]);
    }

    public static function findById(int $id)
    {
        $user = R::load('users', $id);
        return $user->id ? $user : null;
    }

    public static function getPublicProfileById($id)
    {
        $user = R::load('users', $id);
        if (!$user->id) {
            http_response_code(404);
            return [
                'error'   => true,
                'status'  => 404,
                'message' => 'User not found.'
            ];
        }

        $roles = UserRole::getUserRoles($user->id);

        return [
            'error'  => false,
            'status' => 200,
            'data'   => [
                'id'           => $user->id,
                'username'     => $user->username,
                'firstname'    => $user->firstname,
                'lastname'     => $user->lastname,
                'uuid'         => $user->uuid,
                'roles'        => $roles,
                'biography'    => $user->biography,
                'headline'     => $user->headline,
                'user_picture' => Media::getMediaById($user->user_picture),
                'link_website' => $user->link_website,
                'link_x'       => $user->link_x,
                'link_linkedin'  => $user->link_linkedin,
                'link_instagram' => $user->link_instagram,
                'link_facebook'  => $user->link_facebook,
                'link_tiktok'    => $user->link_tiktok,
                'link_youtube'   => $user->link_youtube,
                'link_github'    => $user->link_github,
            ]
        ];
    }

    public static function updateProfile($data)
    {
        $currentUser = AuthController::getCurrentUser();

        if (!$currentUser) {
            return ['error' => true, 'status' => 401, 'message' => 'Unauthorized.'];
        }

        $validator  = new \Rakit\Validation\Validator;
        $validation = $validator->make($data, [
            'firstname' => 'required|max:30|min:2',
            'lastname'  => 'required|max:30|min:2',
        ]);
        $validation->validate();

        if ($validation->fails()) {
            return [
                'error'   => true,
                'status'  => 422,
                'errors'  => $validation->errors()->firstOfAll(),
                'message' => 'Please check the validated fields.'
            ];
        }

        R::begin();
        try {
            $user = R::load('users', $currentUser->user->id);
            if (!$user->id) throw new \Exception('User not found.');

            $user->firstname      = $data['firstname'];
            $user->lastname       = $data['lastname'];
            $user->biography      = $data['biography']      ?? $user->biography;
            $user->headline       = $data['headline']       ?? $user->headline;
            $user->link_website   = $data['link_website']   ?? $user->link_website;
            $user->link_x         = $data['link_x']         ?? $user->link_x;
            $user->link_linkedin  = $data['link_linkedin']  ?? $user->link_linkedin;
            $user->link_instagram = $data['link_instagram'] ?? $user->link_instagram;
            $user->link_facebook  = $data['link_facebook']  ?? $user->link_facebook;
            $user->link_tiktok    = $data['link_tiktok']    ?? $user->link_tiktok;
            $user->link_youtube   = $data['link_youtube']   ?? $user->link_youtube;
            $user->link_github    = $data['link_github']    ?? $user->link_github;
            $user->updated_at     = R::isoDateTime();

            R::store($user);
            R::commit();

            return ['error' => false, 'status' => 200, 'message' => 'Profile updated successfully.', 'data' => $user];
        } catch (\Exception $e) {
            R::rollback();
            return ['error' => true, 'status' => 500, 'errors' => ['general' => 'Transaction failed: ' . $e->getMessage()]];
        }
    }

    public static function uploadPicture($data)
    {
        $currentUser = AuthController::getCurrentUser();

        if (!$currentUser) {
            return ['error' => true, 'status' => 401, 'message' => 'Unauthorized.'];
        }

        $validator  = new \Rakit\Validation\Validator;
        $validation = $validator->make($data, ['user_picture' => 'required']);
        $validation->validate();

        if ($validation->fails()) {
            http_response_code(422);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Please check the validated fields.',
                'errors'  => $validation->errors()->firstOfAll()
            ]);
            exit;
        }

        R::exec("UPDATE users SET user_picture = ? WHERE id = ?", [$data['user_picture'], $currentUser->user->id]);

        return [
            'error'   => false,
            'status'  => 200,
            'data'    => Media::getMediaById($data['user_picture']),
            'message' => 'Profile picture uploaded successfully.',
        ];
    }

    // =========================================================================
    // 2FA METHODS
    // =========================================================================

    /**
     * Step 1: Generate a TOTP secret and return QR code.
     * Called when user opts in to 2FA. Does NOT enable it yet.
     */
    public static function setup2FA()
    {
        $currentUser = AuthController::getCurrentUser();
        $user        = R::load('users', $currentUser->user->id);

        if (!$user->id) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found.']);
            exit;
        }

        if ($user->totp_enabled) {
            http_response_code(400);
            echo json_encode(['error' => '2FA is already enabled.']);
            exit;
        }

        $google2fa = new Google2FA();
        $secret    = $google2fa->generateSecretKey();

        // Save the secret but don't enable yet — user must confirm first
        $user->totp_secret = $secret;
        R::store($user);

        // Build QR code as base64 SVG
        $qrUrl    = $google2fa->getQRCodeUrl(
            env('APP_NAME', 'Upskill'),
            $user->email,
            $secret
        );
        $renderer = new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd());
        $writer   = new Writer($renderer);
        $qrSvg    = base64_encode($writer->writeString($qrUrl));

        echo json_encode([
            'status'  => 'success',
            'secret'  => $secret,  // for manual entry in the app
            'qr_code' => $qrSvg,   // use as: <img src="data:image/svg+xml;base64,..." />
        ]);
        exit;
    }

    /**
     * Step 2: Confirm setup — user submits their first code to prove the app is synced.
     * This enables 2FA and returns one-time backup codes.
     */
    public static function confirm2FA()
    {
        $currentUser = AuthController::getCurrentUser();
        $input       = json_decode(file_get_contents('php://input'), true);
        $code        = trim($input['code'] ?? '');

        $user = R::load('users', $currentUser->user->id);

        if (!$user->id) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found.']);
            exit;
        }

        if ($user->totp_enabled) {
            http_response_code(400);
            echo json_encode(['error' => '2FA is already enabled.']);
            exit;
        }

        if (empty($user->totp_secret)) {
            http_response_code(400);
            echo json_encode(['error' => 'No 2FA setup found. Call /setup-2fa first.']);
            exit;
        }

        $google2fa = new Google2FA();
        if (!$google2fa->verifyKey($user->totp_secret, $code)) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid code. Make sure your authenticator app is synced.']);
            exit;
        }

        // Enable 2FA
        $user->totp_enabled     = 1;
        $user->totp_verified_at = R::isoDateTime();
        R::store($user);

        // Generate backup codes
        $backupCodes = self::generateBackupCodes($user->id);

        echo json_encode([
            'status'       => 'success',
            'message'      => '2FA enabled successfully.',
            'backup_codes' => $backupCodes, // shown ONCE — user must save these
        ]);
        exit;
    }

    /**
     * Step 3 (at login): Verify the 6-digit code after password check.
     * Accepts the pre_auth_token from login response.
     */
    public static function verifyTwoFactorCode(array $input)
    {
        header('Content-Type: application/json');

        // ✅ VALIDATE INPUT WITH RAKIT
        $validator  = new \Rakit\Validation\Validator;
        $validation = $validator->make($input, [
            'code'           => 'required|digits:6',
            'pre_auth_token' => 'required|min:10',
        ], [
            'code:required'             => 'Verification code is required.',
            'code:digits'               => 'Verification code must be exactly 6 digits.',
            'pre_auth_token:required'   => 'Pre-auth token is required.',
            'pre_auth_token:min'        => 'Pre-auth token is invalid or expired.',
        ]);

        $validation->validate();

        if ($validation->fails()) {
            return [
                'status'  => 'error',
                'message' => 'Validation failed.',
                'errors'  => $validation->errors()->firstOfAll(),
                'http_code' => 422
            ];
        }

        // ✅ FIX: Extract and sanitize validated inputs from $input
        $preAuthToken = trim($input['pre_auth_token']);
        $code = trim($input['code']);

        // Decode the pre-auth token
        try {
            $decoded = JWT::decode($preAuthToken, new \Firebase\JWT\Key(env('JWT_SECRET'), 'HS256'));
        } catch (\Exception $e) {
            return [
                'status'  => 'error',
                'message' => 'Invalid or expired session. Please log in again.',
                'http_code' => 401
            ];
        }

        if (($decoded->type ?? '') !== 'pre_auth') {
            return [
                'status'  => 'error',
                'message' => 'Invalid token type.',
                'http_code' => 401
            ];
        }

        $user = R::load('users', $decoded->sub);

        if (!$user->id) {
            return [
                'status'  => 'error',
                'message' => 'User not found.',
                'http_code' => 404
            ];
        }

        // Check if 2FA is actually enabled
        if (!$user->totp_enabled) {
            return [
                'status'  => 'error',
                'message' => '2FA is not enabled for this account.',
                'http_code' => 400
            ];
        }

        // ✅ IMPROVED: Check if totp_secret exists
        if (empty($user->totp_secret)) {
            return [
                'status'  => 'error',
                'message' => '2FA secret not found. Please contact support.',
                'http_code' => 400
            ];
        }

        // Try TOTP code first, then backup codes
        $google2fa = new Google2FA();

        // ✅ IMPROVED: Add debugging
        $validTotp = false;
        try {
            $validTotp = $google2fa->verifyKey($user->totp_secret, $code, 1); // Allow 1 minute drift
        } catch (\Exception $e) {
            error_log("TOTP verification error: " . $e->getMessage());
        }

        // If TOTP fails, try backup codes
        $validBackup = false;
        if (!$validTotp) {
            $validBackup = self::consumeBackupCode($user->id, $code);
        }

        // ✅ IMPROVED: Return specific error message
        if (!$validTotp && !$validBackup) {
            return [
                'status'  => 'error',
                'message' => 'Verification code is incorrect. Please check your authenticator app or use a backup code.',
                'http_code' => 422
            ];
        }

        // Issue full JWT — same structure as login()
        $roles   = UserRole::getUserRoles($user->id);
        $payload = [
            'user' => [
                'id'        => $user->id,
                'username'  => $user->username,
                'firstname' => $user->firstname,
                'lastname'  => $user->lastname,
                'email'     => $user->email,
                'uuid'      => $user->uuid,
                'verified'  => $user->verified,
                'status'    => $user->status,
                'roles'     => $roles,
            ],
            'iat' => time(),
        ];

        $jwt = JWT::encode($payload, env('JWT_SECRET'), 'HS256');

        // ✅ Fix: Get user_picture safely
        $user_picture = $user->user_picture ? Media::getMediaById($user->user_picture) : null;

        return [
            'status' => 'success',
            'message' => '2FA verification successful.',
            'token'  => $jwt,
            'user'   => [
                'id'           => $user->id,
                'username'     => $user->username,
                'firstname'    => $user->firstname,
                'lastname'     => $user->lastname,
                'email'        => $user->email,
                'uuid'         => $user->uuid,
                'verified'     => $user->verified,
                'status'       => $user->status,
                'roles'        => $roles,
                'biography'    => $user->biography,
                'headline'     => $user->headline,
                'user_picture' => $user_picture,
                'link_website' => $user->link_website,
                'link_x'       => $user->link_x,
                'link_linkedin'  => $user->link_linkedin,
                'link_instagram' => $user->link_instagram,
                'link_facebook'  => $user->link_facebook,
                'link_tiktok'    => $user->link_tiktok,
                'link_youtube'   => $user->link_youtube,
                'link_github'    => $user->link_github,
            ],
            'http_code' => 200
        ];
    }

    /**
     * Disable 2FA — requires a valid TOTP code to confirm.
     */
    public static function disable2FA()
    {
        $currentUser = AuthController::getCurrentUser();
        $input       = json_decode(file_get_contents('php://input'), true);
        $code        = trim($input['code'] ?? '');

        $user = R::load('users', $currentUser->user->id);

        if (!$user->id) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found.']);
            exit;
        }

        if (!$user->totp_enabled) {
            http_response_code(400);
            echo json_encode(['error' => '2FA is not enabled.']);
            exit;
        }

        $google2fa = new Google2FA();
        if (!$google2fa->verifyKey($user->totp_secret, $code)) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid code. Confirm with your authenticator app to disable.']);
            exit;
        }

        // Clear all 2FA data
        $user->totp_secret      = null;
        $user->totp_enabled     = 0;
        $user->totp_verified_at = null;
        R::store($user);

        // Delete backup codes
        R::exec('DELETE FROM user_2fa_backup_codes WHERE user_id = ?', [$user->id]);

        echo json_encode([
            'status'  => 'success',
            'message' => '2FA disabled successfully.',
        ]);
        exit;
    }

    /**
     * Get 2FA status for the current user.
     */
    public static function get2FAStatus()
    {
        $currentUser = AuthController::getCurrentUser();
        $user        = R::load('users', $currentUser->user->id);

        if (!$user->id) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found.']);
            exit;
        }

        // Count remaining backup codes
        $remaining = (int) R::getCell(
            'SELECT COUNT(*) FROM user_2fa_backup_codes WHERE user_id = ? AND used_at IS NULL',
            [$user->id]
        );

        echo json_encode([
            'status'                  => 'success',
            'totp_enabled'            => (bool) $user->totp_enabled,
            'totp_verified_at'        => $user->totp_verified_at,
            'backup_codes_remaining'  => $remaining,
        ]);
        exit;
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Generate 8 one-time backup codes, store hashed, return plaintext once.
     */
    private static function generateBackupCodes(int $userId): array
    {
        // Use raw SQL — RedBeanPHP can't dispense bean types with underscores
        R::exec('DELETE FROM user_2fa_backup_codes WHERE user_id = ?', [$userId]);

        $plainCodes = [];

        for ($i = 0; $i < 8; $i++) {
            $code = strtoupper(bin2hex(random_bytes(4))); // e.g. "A1B2C3D4"
            $hash = password_hash($code, PASSWORD_BCRYPT);

            R::exec(
                'INSERT INTO user_2fa_backup_codes (user_id, code_hash, used_at, created_at) VALUES (?, ?, NULL, NOW())',
                [$userId, $hash]
            );

            $plainCodes[] = $code;
        }

        return $plainCodes;
    }
    /**
     * Check if input matches an unused backup code. Marks it used if valid.
     */
    private static function consumeBackupCode(int $userId, string $inputCode): bool
    {
        // ✅ IMPROVED: Validate input before querying
        if (empty($inputCode) || strlen($inputCode) < 6) {
            return false;
        }

        try {
            $rows = R::getAll(
                'SELECT id, code_hash FROM user_2fa_backup_codes WHERE user_id = ? AND used_at IS NULL',
                [$userId]
            );

            foreach ($rows as $row) {
                if (password_verify($inputCode, $row['code_hash'])) {
                    R::exec(
                        'UPDATE user_2fa_backup_codes SET used_at = NOW() WHERE id = ?',
                        [$row['id']]
                    );
                    return true;
                }
            }
        } catch (\Exception $e) {
            error_log("Backup code verification error: " . $e->getMessage());
        }

        return false;
    }
}
