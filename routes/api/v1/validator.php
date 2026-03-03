<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Validator\ValidatorController;

Route::post('validator/scan', [ValidatorController::class, 'scan']);

