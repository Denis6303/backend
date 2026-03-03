<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Payment\PaymentMethodController;

Route::get('payment-methods', [PaymentMethodController::class, 'index']);

