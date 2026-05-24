<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Health check
    Route::get('/ping', fn () => response()->json(['success' => true, 'message' => 'pong', 'data' => null, 'errors' => null]));

    // Auth — public
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::post('/register', 'register');
        Route::post('/login', 'login');
    });

    // Auth — protected
    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('auth')->controller(AuthController::class)->group(function () {
            Route::get('/me', 'me');
            Route::post('/logout', 'logout');
            Route::post('/logout-all', 'logoutAll');
        });
    });
});
