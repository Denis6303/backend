<?php

use App\Http\Controllers\Admin\EventsAdminController;
use App\Http\Controllers\Admin\FundraisingContributionsAdminController;
use App\Http\Controllers\Admin\FundraisingsAdminController;
use App\Http\Controllers\Admin\OccurrencesAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->middleware('auth:api')
    ->group(function () {
        Route::get('/events', [EventsAdminController::class, 'index']);
        Route::post('/events/{id}/verify', [EventsAdminController::class, 'verify']);
        Route::post('/events/{id}/publish', [EventsAdminController::class, 'publish']);
        Route::post('/events/{id}/unpublish', [EventsAdminController::class, 'unpublish']);
        Route::post('/events/{id}/commission', [EventsAdminController::class, 'commission']);
        Route::post('/events/{id}/service-costs', [EventsAdminController::class, 'serviceCosts']);
        Route::post('/events/{id}/assign-admin-owner', [EventsAdminController::class, 'assignAdminOwner']);
        Route::post('/events/{id}/restore-owner', [EventsAdminController::class, 'restoreOwner']);
        Route::get('/events/{id}/owner-history', [EventsAdminController::class, 'ownerHistory']);

        Route::get('/occurrences/{id}/summary', [OccurrencesAdminController::class, 'summary']);
        Route::get('/occurrences/{id}/earnings', [OccurrencesAdminController::class, 'earnings']);
        Route::get('/occurrences/{id}/ticket-types', [OccurrencesAdminController::class, 'ticketTypes']);

        Route::get('/fundraisings', [FundraisingsAdminController::class, 'index']);
        Route::post('/fundraisings/{id}/verify', [FundraisingsAdminController::class, 'verify']);
        Route::get('/fundraisings/{id}/contributions', [FundraisingsAdminController::class, 'contributions']);
        Route::get('/fundraising-contributions', [FundraisingContributionsAdminController::class, 'index']);
    });

