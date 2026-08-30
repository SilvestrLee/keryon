<?php

return [
    // Explicit placeholders for development/proof only. Production delivery
    // remains gated on Legal-approved immutable policy identifiers.
    'legal' => [
        'terms_version' => env('KERYON_TERMS_VERSION', 'development-terms-v1'),
        'privacy_version' => env('KERYON_PRIVACY_VERSION', 'development-privacy-v1'),
    ],
];
