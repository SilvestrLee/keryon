<?php

return [
    // Explicit placeholders for development/proof only. Outside local/testing,
    // a missing identifier resolves to null and the acceptance flow fails
    // closed (HTTP 503) rather than silently substituting a development
    // fixture for an approved legal policy version. Mirrors config/staff.php.
    'legal' => [
        'terms_version' => env('KERYON_TERMS_VERSION', in_array(env('APP_ENV'), ['local', 'testing'], true) ? 'development-terms-v1' : null),
        'privacy_version' => env('KERYON_PRIVACY_VERSION', in_array(env('APP_ENV'), ['local', 'testing'], true) ? 'development-privacy-v1' : null),
    ],
];
