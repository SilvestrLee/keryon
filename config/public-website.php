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
        // Accepted values: 'unavailable' (default, fail-closed), 'fake'
        // (non-production only), 'cloudflare' (see config/cloudflare.php).
        'dns_resolver' => env('CHURCH_DOMAIN_DNS_RESOLVER', 'unavailable'),
        'provisioner' => env('CHURCH_DOMAIN_PROVISIONER', 'unavailable'),
        'quarantine_days' => 30,
        'claim_limit' => 2,

        // K-DOMAIN-001E operational cadence — safe V1 defaults, not
        // Cloudflare limits. See config/cloudflare.php for provider config.
        'health_check_interval_hours' => (int) env('CHURCH_DOMAIN_HEALTH_INTERVAL_HOURS', 12),
        'tls_poll_interval_minutes' => (int) env('CHURCH_DOMAIN_TLS_POLL_INTERVAL_MINUTES', 5),
        'dispatch_batch_size' => (int) env('CHURCH_DOMAIN_DISPATCH_BATCH_SIZE', 50),
        // Keryon-internal safety bound on provider-consuming domain jobs —
        // see AppServiceProvider's 'domain-provider' rate limiter.
        'provider_rate_limit_per_minute' => (int) env('CHURCH_DOMAIN_PROVIDER_RATE_LIMIT_PER_MINUTE', 30),
    ],
];
