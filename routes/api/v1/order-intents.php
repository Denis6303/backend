<?php

use App\Http\Controllers\Api\V1\Order\OrderIntentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Order intents (ticket purchase: reserve → checkout → verify → order)
|--------------------------------------------------------------------------
| Middleware user_or_client: Bearer utilisateur Passport ou jeton client OAuth.
*/
Route::middleware('user_or_client')->prefix('order-intents')->group(function () {
    Route::post('create', [OrderIntentController::class, 'store']);
    Route::get('{key}', [OrderIntentController::class, 'show'])->whereUuid('key');
    Route::post('{key}/checkout', [OrderIntentController::class, 'checkout'])->whereUuid('key');
    Route::post('{key}/verify', [OrderIntentController::class, 'verify'])->whereUuid('key');
    Route::post('{key}/cancel', [OrderIntentController::class, 'cancel'])->whereUuid('key');
});
