<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\FundraisingController as AdminFundraisingController;

Route::get('fundraisings', [AdminFundraisingController::class, 'index']);
Route::post('fundraisings/{id}/verify', [AdminFundraisingController::class, 'verify']);
Route::get('fundraisings/{id}/contributions', [AdminFundraisingController::class, 'contributions']);

