<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Order\OrderIntentController;

Route::post('order-intents/create', [OrderIntentController::class, 'create']);
Route::post('order-intents/{key}/checkout', [OrderIntentController::class, 'checkout']);
Route::post('order-intents/{key}/verify', [OrderIntentController::class, 'verify']);

