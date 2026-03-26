<?php

require_once __DIR__ . '/../models/PayoutAccount.php';

class PayoutAccountController
{
    public static function createPayoutAccount()
    {
        try {
            $input  = json_decode(file_get_contents('php://input'), true);
            $result = PayoutAccount::create($input);

            if (!empty($result['error'])) {
                http_response_code($result['status'] ?? 500);
                echo json_encode([
                    'message' => $result['message'] ?? 'An error occurred.',
                    'errors'  => $result['errors'] ?? null,
                ]);
                return;
            }
            echo json_encode($result);
        } catch (Exception $e) {
            // Handle exceptions
            return ['error' => $e->getMessage()];
        }
    }

    public static function getCurrentUserPayoutAccounts()
    {
        try {
            $accounts = PayoutAccount::currentUserPayoutAccounts();
            echo json_encode($accounts);
        } catch (Exception $e) {
            // Handle exceptions
            http_response_code(500);
            echo json_encode(['error' => 'An error occurred while fetching payout accounts.']);
        }
    }
}
