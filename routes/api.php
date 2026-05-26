<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BoothController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\ShareController;
use App\Http\Controllers\Api\V1\TemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Health check
    Route::get('/ping', fn () => response()->json(['success' => true, 'message' => 'pong', 'data' => null, 'errors' => null]));

    // ── Auth — public ─────────────────────────────────────────────────────────
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::post('/register', 'register');
        Route::post('/login', 'login');
    });

    // ── Booth — public (session_token dalam request body) ────────────────────
    Route::prefix('booth')->controller(BoothController::class)->group(function () {
        Route::post('/start-session',       'startSession');
        Route::post('/upload-photo',        'uploadPhoto');
        Route::post('/complete-session',    'completeSession');
        Route::get('/session/{shareToken}', 'showSession');
    });

    // ── Share — public (share_token di URL) ──────────────────────────────────
    Route::prefix('share/{shareToken}')->controller(ShareController::class)->group(function () {
        Route::get('/qr',        'qr');        // Generate & return QR code PNG
        Route::post('/email',    'email');     // Kirim email galeri ke tamu
        Route::post('/whatsapp', 'whatsapp'); // Generate WhatsApp deep link
    });

    // ── Download — public (foto final dengan signed URL 1 jam) ───────────────
    Route::get('/download/{photoId}', [ShareController::class, 'download']);

    // ── Protected routes (Bearer token) ──────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::prefix('auth')->controller(AuthController::class)->group(function () {
            Route::get('/me',          'me');
            Route::post('/logout',     'logout');
            Route::post('/logout-all', 'logoutAll');
        });

        // Events
        Route::prefix('events')->controller(EventController::class)->group(function () {
            Route::get('/',                   'index');
            Route::post('/',                  'store');
            Route::get('/{slug}',             'show');               // lookup by slug
            Route::put('/{event}',            'update');             // model binding by ID
            Route::delete('/{event}',         'destroy');            // model binding by ID
            Route::patch('/{event}/toggle-active', 'activate');      // toggle aktif/nonaktif
            Route::get('/{event}/sessions',   'sessions');           // sessions + photos per event
        });

        // Templates
        Route::prefix('templates')->controller(TemplateController::class)->group(function () {
            Route::get('/',            'index');
            Route::post('/',           'store');
            Route::get('/{template}',  'show');
            Route::put('/{template}',  'update');
            Route::delete('/{template}', 'destroy');
        });
    });
});
