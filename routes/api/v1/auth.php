<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\VerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;

/**
 * @group User authentication
 *
 * Endpoints pour l'authentification utilisateur (Passport Bearer token),
 * vérification d'email, et reset password.
 */
Route::prefix('auth')->group(function () {
    // Auth core
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');

    Route::post('email/resend', [VerificationController::class, 'resend'])
        ->middleware('auth:api');

    // Password reset
    Route::post('forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])
        ->name('password.email');
});
