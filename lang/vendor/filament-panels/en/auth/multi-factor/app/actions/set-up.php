<?php

return [
    'label' => 'Set up',
    'modal' => [
        'heading' => 'Set up your authenticator app',
        'description' => 'Use any compatible authenticator application. Keryon will never ask you to share the secret or a recovery code.',
        'content' => [
            'qr_code' => ['instruction' => '1. Scan this QR code with your authenticator app:', 'alt' => 'QR code for Keryon Central multi-factor authentication'],
            'text_code' => ['instruction' => 'Or enter this code manually:', 'messages' => ['copied' => 'Copied']],
            'recovery_codes' => ['instruction' => '3. Save these one-time recovery codes securely offline. They are shown only once and are required if you lose your authenticator.'],
        ],
        'form' => [
            'code' => [
                'label' => '2. Enter the 6-digit code', 'validation_attribute' => 'authentication code',
                'below_content' => 'This proves your authenticator is configured before privileged access is granted.',
                'messages' => ['invalid' => 'The authentication code is invalid.', 'rate_limited' => 'Too many attempts. Try again later.'],
            ],
        ],
        'actions' => ['submit' => ['label' => 'Enable multi-factor authentication']],
    ],
    'notifications' => ['enabled' => ['title' => 'Multi-factor authentication enabled']],
];
