<?php

return [

    /*
     * Paths yang dicakup CORS.
     * 'api/*'            → semua endpoint API
     * 'broadcasting/auth' → Pusher private channel auth
     */
    'paths' => ['api/*', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    /*
     * Di production: baca dari FRONTEND_URL di .env
     * Contoh: https://yourdomain.com
     *
     * Support multiple origins dengan array:
     * FRONTEND_URL=https://yourdomain.com,https://www.yourdomain.com
     */
    'allowed_origins' => array_filter(
        array_map('trim', explode(',', env('FRONTEND_URL', 'http://localhost:5173')))
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    /*
     * false = pakai Bearer token (Sanctum stateless API).
     * Jangan set true kecuali pakai cookie-based session.
     */
    'supports_credentials' => false,

];
