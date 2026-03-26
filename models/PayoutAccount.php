<?php

use RedBeanPHP\R;

require_once __DIR__ . '/../controllers/AuthController.php';

use Ramsey\Uuid\Uuid;

class PayoutAccount
{
    public static function create($input)
    {
        $auth = AuthController::getCurrentUser();
        $userId = $auth->user->id ?? null;

        if (!$userId) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        // ✅ Validate input
        $provider  = strtolower(trim($input['provider'] ?? ''));
        $accountId = trim($input['accountId'] ?? '');
        $email     = trim($input['email'] ?? '');

        if (!in_array($provider, ['paypal', 'stripe'], true)) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid provider']);
            return;
        }

        if ($accountId === '') {
            http_response_code(422);
            echo json_encode(['error' => 'accountId is required']);
            return;
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            echo json_encode(['error' => 'Valid email is required']);
            return;
        }

        // ✅ Check if payout account already exists
        $existing = R::findOne(
            'payout_accounts',
            'user_id = ? AND provider = ?',
            [$userId, $provider]
        );

        if ($existing) {
            // 🔁 UPDATE
            $existing->provider_account_id = $accountId;
            $existing->provider_email = $email;

            R::exec(
                'UPDATE payout_accounts SET provider_account_id = ?, provider_email = ? WHERE id = ?',
                [$accountId, $email, $existing->id]
            );

            echo json_encode([
                'success' => true,
                'message' => 'Payout account updated successfully'
            ]);
            return;
        }

        // ✅ Determine if this should be the default account
        $defaultExists = R::getCell(
            'SELECT COUNT(*) FROM payout_accounts WHERE user_id = ? AND is_default = 1',
            [$userId]
        );

        $isDefault = ($defaultExists == 0) ? 1 : 0;

        // 🔹 INSERT new payout account
        R::exec(
            'INSERT INTO payout_accounts (user_id, provider, provider_account_id, provider_email, is_default, uuid) VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $provider, $accountId, $email, $isDefault, Uuid::uuid4()->toString()]
        );

        echo json_encode([
            'success' => true,
            'message' => 'Payout account created successfully',
            'is_default' => (bool)$isDefault
        ]);
    }

    public static function currentUserPayoutAccounts()
    {
        $user = AuthController::getCurrentUser();

        return R::getAll(
            'SELECT id, provider, provider_email, is_default, status, created_at FROM payout_accounts WHERE user_id = ?',
            [$user->user->id]
        );
    }
}
