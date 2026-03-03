<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\DiscountCode\DiscountCodeController;

Route::post('discount-codes/validate', [DiscountCodeController::class, 'validateCode']);

