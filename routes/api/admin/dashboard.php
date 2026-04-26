<?php

use App\Http\Controllers\Api\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

/**
 * @group Admin - Dashboard
 *
 * Données consolidées du dashboard admin.
 */
Route::prefix('dashboard')->group(function () {
    Route::controller(DashboardController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::get('kpis', 'kpis');
        Route::get('sales-overview', 'salesOverview');
        Route::get('recent-activity', 'recentActivity');
        Route::get('upcoming-events', 'upcomingEvents');
        Route::get('recent-orders', 'recentOrders');
        Route::get('payouts', 'payouts');
    });
});

