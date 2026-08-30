<?php

return [
    'current_product' => [
        'plan_slug' => env('KERYON_CURRENT_PLAN', 'keryon'),
        'plan_version' => env('KERYON_CURRENT_PLAN_VERSION', 'keryon-2026-1'),
    ],

    // Temporary migration bridge: only Churches with no Subscription history
    // may use the pre-billing default. Once commercial history exists, an
    // inactive/expired Subscription fails closed instead of falling back.
    'transitional_subscription_fallback' => env('KERYON_TRANSITIONAL_SUBSCRIPTION_FALLBACK', true),
];
