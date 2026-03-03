<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('user')->group(function () {
    // TODO: OrderIntentController (user-specific listing/history) routes
});

