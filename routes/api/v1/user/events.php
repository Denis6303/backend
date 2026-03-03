<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Event\EventController;

Route::middleware('auth:api')->prefix('user')->group(function () {
    Route::get('events', [EventController::class, 'userIndex']);
});

