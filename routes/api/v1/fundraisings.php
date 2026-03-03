<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Fundraising\FundraisingController;

Route::get('fundraisings', [FundraisingController::class, 'index']);
Route::get('fundraisings/{idOrSlug}', [FundraisingController::class, 'show']);
Route::get('fundraisings/{idOrSlug}/contributions', [FundraisingController::class, 'contributions']);

