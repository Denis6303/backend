<?php

use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\VerificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Point d'entrée unique pour l'API.
| - /api/v1/*     : API versionnée (routes/api/v1.php)
| - /api/admin/*  : API admin (routes/api/admin.php)
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

// Routes globales (non versionnées) nécessaires aux notifications Laravel
Route::get('auth/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
    ->middleware('signed')
    ->name('verification.verify');

Route::get('reset-password/{token}', [PasswordResetController::class, 'redirectToFrontend'])
    ->name('password.reset');

// /api/v1/*
Route::prefix('{version}')
    ->whereIn('version', ['v1'])
    ->group(base_path('routes/api/v1.php'));

// /api/admin/*
Route::prefix('admin')
    ->middleware(['auth:api', 'admin'])
    ->group(base_path('routes/api/admin.php'));
