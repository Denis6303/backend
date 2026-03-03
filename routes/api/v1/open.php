<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Open\OpenController;

Route::get('open/ping', [OpenController::class, 'ping']);

