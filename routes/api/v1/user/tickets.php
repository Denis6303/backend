<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\User\TicketController;

Route::middleware('auth:api')->prefix('users/me')->group(function () {
    Route::get('tickets', [TicketController::class, 'myTickets']);
    Route::get('tickets/transferred', [TicketController::class, 'myTransferredTickets']);
    Route::get('tickets/{id}', [TicketController::class, 'myTicket']);
    Route::post('tickets/{id}/transfer', [TicketController::class, 'transferTicket']);
    Route::post('tickets/{id}/update-transferred-ticket', [TicketController::class, 'updateTransferredTicketEmail']);
    Route::post('tickets/{id}/cancel', [TicketController::class, 'cancelTicket']);
});

