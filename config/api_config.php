<?php
/**
 * API Configuration
 *
 * This file is intentionally kept out of source control and loads sensitive
 * values from environment variables. Use .env to manage these values in local
 * development and your secret manager in production.
 */

return [
    'scraper_api' => [
        'api_key' => getenv('SCRAPER_API_KEY') ?: null,
        'api_key_hash' => getenv('SCRAPER_API_KEY_HASH') ?: null,
        'api_key_backup' => getenv('SCRAPER_API_KEY_BACKUP') ?: null,
        'api_key_backup_hash' => getenv('SCRAPER_API_KEY_BACKUP_HASH') ?: null,
        'token_ttl' => 86400,
        'allowed_ips' => array_filter(
            array_map('trim', explode(',', getenv('SCRAPER_ALLOWED_IPS') ?: '')),
            fn($ip) => $ip !== ''
        ),
    ],

    'mobile_api' => [
        'api_key' => getenv('MOBILE_API_KEY') ?: null,
        'api_key_hash' => getenv('MOBILE_API_KEY_HASH') ?: null,
        'api_key_backup' => null,
        'api_key_backup_hash' => null,
        'token_ttl' => 3600,
        'allowed_ips' => [],
    ],

    'external_api' => [
        'api_key' => getenv('EXTERNAL_API_KEY') ?: null,
        'api_key_hash' => getenv('EXTERNAL_API_KEY_HASH') ?: null,
        'api_key_backup' => null,
        'api_key_backup_hash' => null,
        'token_ttl' => 3600,
        'allowed_ips' => [],
    ],

    'rate_limits' => [
        'scraper' => ['requests' => 100, 'window' => 3600],
        'mobile' => ['requests' => 1000, 'window' => 3600],
        'external' => ['requests' => 500, 'window' => 3600],
    ],

    'brute_force' => [
        'max_failures' => 10,
        'block_duration' => 3600,
    ],
];
