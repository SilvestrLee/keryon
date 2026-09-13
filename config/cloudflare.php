<?php

return [
    // Cloudflare DNS-over-HTTPS resolver (App\Domain\Dns\CloudflareDnsResolver).
    // Public, unauthenticated endpoint — no API token involved.
    'dns' => [
        'endpoint' => env('CLOUDFLARE_DOH_ENDPOINT', 'https://cloudflare-dns.com/dns-query'),
        'connect_timeout' => (int) env('CLOUDFLARE_DOH_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('CLOUDFLARE_DOH_TIMEOUT', 5),
        'retry_times' => (int) env('CLOUDFLARE_DOH_RETRY_TIMES', 0),
        'retry_delay_ms' => (int) env('CLOUDFLARE_DOH_RETRY_DELAY_MS', 200),
    ],

    // Cloudflare for SaaS / Custom Hostnames API
    // (App\Domain\Provisioning\CloudflareDomainProvisioner).
    // Requires a zone-scoped API token with "SSL and Certificates" Write.
    'provisioner' => [
        'api_base_url' => env('CLOUDFLARE_API_BASE_URL', 'https://api.cloudflare.com/client/v4'),
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        // Keryon-owned origin Cloudflare should connect to for these custom
        // hostnames. Production must set its own value — never hardcode
        // origin.staging.keryon.app or any other origin in adapter code.
        'custom_origin_server' => env('CLOUDFLARE_CUSTOM_ORIGIN_SERVER'),
        'validation_method' => env('CLOUDFLARE_SSL_VALIDATION_METHOD', 'http'),
        'min_tls_version' => env('CLOUDFLARE_MIN_TLS_VERSION', '1.2'),
        'connect_timeout' => (int) env('CLOUDFLARE_API_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('CLOUDFLARE_API_TIMEOUT', 15),
    ],
];
