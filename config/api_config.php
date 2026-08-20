<?php
/**
 * API Configuration
 *
 * This file is intentionally kept out of source control and loads sensitive
 * values from environment variables. Use .env to manage these values in local
 * development and your secret manager in production.
 */

return array (
  'scraper_api' =>
  array (
    'api_key' => NULL,
    'api_key_hash' => NULL,
    'api_key_backup' => NULL,
    'api_key_backup_hash' => NULL,
    'token_ttl' => 86400,
    'allowed_ips' =>
    array (
    ),
  ),
  'mobile_api' =>
  array (
    'api_key' => NULL,
    'api_key_hash' => NULL,
    'api_key_backup' => NULL,
    'api_key_backup_hash' => NULL,
    'token_ttl' => 3600,
    'allowed_ips' =>
    array (
    ),
  ),
   'external_api' =>
   array (
     'api_key' => NULL,
     'api_key_hash' => NULL,
     'api_key_backup' => NULL,
     'api_key_backup_hash' => NULL,
     'token_ttl' => 3600,
     'allowed_ips' =>
     array (
     ),
   ),
   'bps_api' =>
   array (
     'api_key' => getenv('BPS_API_KEY') ?: NULL,
     'base_url' => getenv('BPS_API_BASE_URL') ?: 'https://webapi.bps.go.id/v1',
     'timeout' => (int)(getenv('BPS_API_TIMEOUT') ?: 30),
     'default_prov_code' => '35',
     'rate_limit' =>
     array (
       'requests' => 100,
       'window' => 60,
     ),
     'allowed_ips' =>
     array (
     ),
   ),
  'rate_limits' =>
  array (
    'scraper' =>
    array (
      'requests' => 100,
      'window' => 3600,
    ),
    'mobile' =>
    array (
      'requests' => 1000,
      'window' => 3600,
    ),
    'external' =>
    array (
      'requests' => 500,
      'window' => 3600,
    ),
  ),
  'brute_force' =>
  array (
    'max_failures' => 10,
    'block_duration' => 3600,
  ),
  'qwen_editor_api' =>
  array (
    'api_key' => $_ENV['QWEN_API_KEY'] ?? NULL,
    'api_secret' => $_ENV['QWEN_API_SECRET'] ?? NULL,
    'refresh_token' => $_ENV['QWEN_REFRESH_TOKEN'] ?? NULL,
    'client_id' => $_ENV['QWEN_CLIENT_ID'] ?? NULL,
    'client_secret' => $_ENV['QWEN_CLIENT_SECRET'] ?? NULL,
    'token_url' => $_ENV['QWEN_TOKEN_URL'] ?? 'https://api.qwen.com/v1/oauth/token',
    'token_ttl' => 3600,
    'allowed_ips' =>
    array (
    ),
  ),
);
