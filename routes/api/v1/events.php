<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Event\EventController;

Route::get('events', [EventController::class, 'index']);
Route::get('events/{idOrSlug}', [EventController::class, 'show']);

