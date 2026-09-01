<?php

return [
    // Production: keryon.app -> {church}.keryon.app.
    // Local teams may use PUBLIC_WEBSITE_BASE_DOMAIN=keryon.test and map
    // fixture hosts in /etc/hosts without changing resolver code.
    'base_domain' => env('PUBLIC_WEBSITE_BASE_DOMAIN', 'keryon.app'),
    'scheme' => env('PUBLIC_WEBSITE_SCHEME', 'https'),
    'asset_origin' => rtrim(env('PUBLIC_MEDIA_ORIGIN', env('APP_URL', 'http://localhost')), '/'),
    'reserved_subdomains' => [
        'www', 'app', 'central', 'api', 'admin', 'mail', 'support', 'status',
        'assets', 'static', 'cdn',
    ],
    'marketing_hosts' => array_values(array_filter(array_map('trim', explode(',', env('KERYON_MARKETING_HOSTS', 'keryon.app,www.keryon.app'))))),
    'application_hosts' => array_values(array_filter(array_map('trim', explode(',', env('KERYON_APPLICATION_HOSTS', 'app.keryon.app'))))),
    'local_hosts' => array_values(array_filter(array_map('trim', explode(',', env('KERYON_LOCAL_HOSTS', 'localhost,127.0.0.1'))))),
    'custom_domains' => [
        'dns_ingress_target' => env('CHURCH_DOMAIN_INGRESS_TARGET'),
        'apex_ipv4' => array_values(array_filter(array_map('trim', explode(',', env('CHURCH_DOMAIN_APEX_IPV4', ''))))),
        'apex_ipv6' => array_values(array_filter(array_map('trim', explode(',', env('CHURCH_DOMAIN_APEX_IPV6', ''))))),
        'dns_resolver' => env('CHURCH_DOMAIN_DNS_RESOLVER', 'unavailable'),
        'provisioner' => env('CHURCH_DOMAIN_PROVISIONER', 'unavailable'),
        'quarantine_days' => 30,
        'claim_limit' => 2,
    ],
];
