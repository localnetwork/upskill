<?php

use RedBeanPHP\R;
use Ramsey\Uuid\Uuid;

class PaymentMethodSeeder
{
    public static $weight = 10; // optional, lower = first

    public function run(): void
    {
        $paymentMethods = [
            [
                'uuid' => Uuid::uuid4()->toString(),
                'name' => 'Paypal',
                'gateway' => 'paypal'
            ],
        ];

        foreach ($paymentMethods as $data) {
            // Check if payment method already exists by name
            $existing = R::findOne('payment_methods', 'name = ?', [$data['name']]);

            if ($existing) {
                echo "⚠️  Skipped duplicate payment method: {$data['name']}\n";
                continue;
            }

            R::exec(
                "INSERT INTO payment_methods (uuid, name, created_at, updated_at)
                 VALUES (?, ?, ?, ?)",
                [$data['uuid'], $data['name'], R::isoDateTime(), R::isoDateTime()]
            );

            echo "✅ Inserted payment method: {$data['name']} ({$data['uuid']})\n";
        }

        echo "✅ PaymentMethodSeeder completed.\n";
    }
}
