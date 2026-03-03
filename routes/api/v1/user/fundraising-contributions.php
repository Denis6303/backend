<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('user')->group(function () {
    // TODO: FundraisingContributionController (user scope) routes
});

