<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Fundraising\FundraisingPaymentIntentController;

Route::post('fundraising-payment-intents/create', [FundraisingPaymentIntentController::class, 'create']);
Route::post('fundraising-payment-intents/{key}/checkout', [FundraisingPaymentIntentController::class, 'checkout']);
Route::post('fundraising-payment-intents/{key}/verify', [FundraisingPaymentIntentController::class, 'verify']);
Route::post('fundraising-payment-intents/{key}/message', [FundraisingPaymentIntentController::class, 'message']);
Route::post('fundraising-payment-intents/{key}/email', [FundraisingPaymentIntentController::class, 'email']);

