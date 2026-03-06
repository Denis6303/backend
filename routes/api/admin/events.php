<?php

use Illuminate\Support\Facades\Route;

/**
 * @group Admin - Events
 *
 * Endpoints d'administration des événements.
 */
use App\Http\Controllers\Api\Admin\EventController as AdminEventController;

Route::get('events', [AdminEventController::class, 'index']);
Route::post('events/{id}/verify', [AdminEventController::class, 'verify']);
Route::post('events/{id}/publish', [AdminEventController::class, 'publish']);
Route::post('events/{id}/unpublish', [AdminEventController::class, 'unpublish']);
Route::post('events/{id}/commission', [AdminEventController::class, 'commission']);
Route::post('events/{id}/service-costs', [AdminEventController::class, 'serviceCosts']);
Route::post('events/{id}/assign-admin-owner', [AdminEventController::class, 'assignAdminOwner']);
Route::post('events/{id}/restore-owner', [AdminEventController::class, 'restoreOwner']);
Route::get('events/{id}/owner-history', [AdminEventController::class, 'ownerHistory']);

