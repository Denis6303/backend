<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\EventsController;
use App\Http\Controllers\Api\V1\FundraisingsController;
use App\Http\Controllers\Api\V1\FundraisingPaymentIntentsController;
use App\Http\Controllers\Api\V1\OrderIntentsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function () {
    Route::get('/events', [EventsController::class, 'index']);
    Route::get('/events/{idOrSlug}', [EventsController::class, 'show']);

    Route::post('/order-intents/create', [OrderIntentsController::class, 'create']);
    Route::post('/order-intents/{key}/checkout', [OrderIntentsController::class, 'checkout']);
    Route::post('/order-intents/{key}/verify', [OrderIntentsController::class, 'verify']);

    Route::get('/fundraisings', [FundraisingsController::class, 'index']);
    Route::get('/fundraisings/{idOrSlug}', [FundraisingsController::class, 'show']);
    Route::get('/fundraisings/{idOrSlug}/contributions', [FundraisingsController::class, 'contributions']);

    Route::post('/fundraising-payment-intents/create', [FundraisingPaymentIntentsController::class, 'create']);
    Route::post('/fundraising-payment-intents/{key}/checkout', [FundraisingPaymentIntentsController::class, 'checkout']);
    Route::post('/fundraising-payment-intents/{key}/verify', [FundraisingPaymentIntentsController::class, 'verify']);
    Route::post('/fundraising-payment-intents/{key}/message', [FundraisingPaymentIntentsController::class, 'message']);
    Route::post('/fundraising-payment-intents/{key}/email', [FundraisingPaymentIntentsController::class, 'email']);
});
