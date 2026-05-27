<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Application Storage Disk
    |--------------------------------------------------------------------------
    |
    | This value controls which disk StorageService uses for user-uploaded
    | files (photos, template previews, etc.).
    |
    | local_public — stores files in storage/app/public/snapbooth and serves
    |                them via APP_URL/storage/snapbooth. No external credentials
    |                needed; use this for development and testing.
    |
    | r2           — uploads to Cloudflare R2. Requires R2_* vars in .env.
    |
    */

    'storage_disk' => env('STORAGE_DISK', 'r2'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // ── Local testing disk ────────────────────────────────────────────────
        // Drop-in replacement for R2 when developing locally.
        // Run `php artisan storage:link` once to activate the symlink.
        // Files land in storage/app/public/snapbooth/** and are served at
        // APP_URL/storage/snapbooth/**  — same path structure as R2.
        'local_public' => [
            'driver'     => 'local',
            'root'       => storage_path('app/public/snapbooth'),
            'url'        => env('APP_URL', 'http://localhost:8000') . '/storage/snapbooth',
            'visibility' => 'public',
            'throw'      => true,
        ],

        // ── Production disk for shared hosting (no symlink support) ───────────
        // Stores files directly inside public/storage/snapbooth/ so they are
        // accessible via web server without needing a symlink.
        'direct_public' => [
            'driver'     => 'local',
            'root'       => public_path('storage/snapbooth'),
            'url'        => env('APP_URL', 'http://localhost:8000') . '/storage/snapbooth',
            'visibility' => 'public',
            'throw'      => true,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        // Cloudflare R2 — S3-compatible storage
        // R2_URL      = public CDN (misal https://cdn.snapbooth.com) — opsional
        // R2_ENDPOINT = https://{accountId}.r2.cloudflarestorage.com
        'r2' => [
            'driver'                  => 's3',
            'key'                     => env('R2_ACCESS_KEY_ID'),
            'secret'                  => env('R2_SECRET_ACCESS_KEY'),
            'region'                  => env('R2_DEFAULT_REGION', 'auto'),
            'bucket'                  => env('R2_BUCKET', 'snapbooth'),
            'url'                     => env('R2_URL'),
            'endpoint'                => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'throw'                   => true,
            'report'                  => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
