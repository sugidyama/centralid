<?php

/**
 * CORS（クロスオリジンリソース共有）設定
 *
 * 許可オリジンは .env の CORS_ALLOWED_ORIGINS にカンマ区切りで列挙する。
 * 例: CORS_ALLOWED_ORIGINS=https://lunchmap.example.com,https://other.example.com
 */

$allowAll = env('CORS_ALLOW_ALL', false);

$allowedOrigins = $allowAll ? ['*'] : array_values(
    array_filter(
        array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', ''))),
        fn (string $o): bool => $o !== ''
    )
);

return [

    'paths' => ['*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,

];
