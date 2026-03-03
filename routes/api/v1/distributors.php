<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Distributor\DistributorController;

Route::middleware('auth:api')->group(function () {
    Route::get('distributor/profile', [DistributorController::class, 'profile']);
});

