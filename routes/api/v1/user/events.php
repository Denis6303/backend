<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Event\EventController;

Route::middleware('auth:api')->prefix('user')->group(function () {
    Route::get('events', [EventController::class, 'userIndex']);
});

// Organizer-friendly "me" endpoints, as described in the Events prompt
Route::middleware('auth:api')->prefix('users/me')->group(function () {
    Route::get('events', [EventController::class, 'userIndex']);
    Route::get('events/{id}', [EventController::class, 'userShow']);
});

