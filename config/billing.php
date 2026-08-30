<?php

return [
    'providers' => [
        'paystack' => [
            'enabled' => env('PAYSTACK_ENABLED', false),
            'environment' => env('PAYSTACK_ENVIRONMENT', 'test'),
            'governance_status' => 'sandbox_only',
            'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
            'account_key' => env('PAYSTACK_ACCOUNT_KEY', 'ng_test'),
            'connect_timeout' => (int) env('PAYSTACK_CONNECT_TIMEOUT', 5),
            'timeout' => (int) env('PAYSTACK_TIMEOUT', 15),
            'enforce_webhook_ips' => env('PAYSTACK_ENFORCE_WEBHOOK_IPS', false),
            'webhook_ips' => ['52.31.139.75', '52.49.173.169', '52.214.14.220'],
        ],
    ],
];
