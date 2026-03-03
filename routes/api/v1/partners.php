<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Partners\EventController as PartnerEventController;
use App\Http\Controllers\Api\V1\Partners\OrderController as PartnerOrderController;
use App\Http\Controllers\Api\V1\Partners\OrderIntentController as PartnerOrderIntentController;
use App\Http\Controllers\Api\V1\Partners\FinanceController as PartnerFinanceController;
use App\Http\Controllers\Api\V1\Partners\WebhookController as PartnerWebhookController;

Route::prefix('partners')->group(function () {
    Route::get('events', [PartnerEventController::class, 'index']);
    Route::post('orders', [PartnerOrderController::class, 'store']);
    Route::post('order-intents', [PartnerOrderIntentController::class, 'store']);
    Route::get('finance/summary', [PartnerFinanceController::class, 'summary']);
    Route::post('webhooks/{provider}', [PartnerWebhookController::class, 'handle']);
});

