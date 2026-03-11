<?php

use App\Http\Controllers\Api\V1\Event\EventDraftController;
use Illuminate\Support\Facades\Route;

/**
 * @group Event Draft
 *
 * Endpoints de gestion des brouillons d'événements pour l'utilisateur connecté.
 */
Route::middleware('auth:api')->prefix('event-drafts')->group(function () {
    Route::get('/', [EventDraftController::class, 'myEventDrafts']);
    Route::get('{id}', [EventDraftController::class, 'myEventDraft']);
    Route::delete('{id}', [EventDraftController::class, 'myDestroy']);

    Route::post('step1', [EventDraftController::class, 'storeStep1']);
    Route::post('{id}/step2', [EventDraftController::class, 'storeStep2']);
    Route::post('{id}/step3', [EventDraftController::class, 'storeStep3']);
    Route::post('{id}/finalize', [EventDraftController::class, 'finalizeEventDraft']);
});

