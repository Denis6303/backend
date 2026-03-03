<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\User\UserController;

Route::middleware('auth:api')->group(function () {
    Route::get('me', [UserController::class, 'me']);
});

