<?php

return [
    // Production: keryon.app -> {church}.keryon.app.
    // Local teams may use PUBLIC_WEBSITE_BASE_DOMAIN=keryon.test and map
    // fixture hosts in /etc/hosts without changing resolver code.
    'base_domain' => env('PUBLIC_WEBSITE_BASE_DOMAIN', 'keryon.app'),
    'scheme' => env('PUBLIC_WEBSITE_SCHEME', 'https'),
    'reserved_subdomains' => ['www', 'app', 'central'],
];
