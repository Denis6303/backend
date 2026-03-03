<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Ce fichier ne fait que router vers les groupes versionnés et admin.
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

// /api/v1/*
Route::prefix('{version}')
    ->whereIn('version', ['v1'])
    ->group(function () {
        require base_path('routes/api/v1/auth.php');
        require base_path('routes/api/v1/passport.php');
        require base_path('routes/api/v1/events.php');
        require base_path('routes/api/v1/order-intents.php');
        require base_path('routes/api/v1/fundraisings.php');
        require base_path('routes/api/v1/fundraising-payment-intents.php');
        require base_path('routes/api/v1/payment-methods.php');
        require base_path('routes/api/v1/users.php');
        require base_path('routes/api/v1/open.php');
        require base_path('routes/api/v1/categories.php');
        require base_path('routes/api/v1/discount-codes.php');
        require base_path('routes/api/v1/distributors.php');
        require base_path('routes/api/v1/partners.php');
        require base_path('routes/api/v1/webhooks.php');
        require base_path('routes/api/v1/validator.php');

        // user/* sous /api/{version}/user/*
        require base_path('routes/api/v1/user/events.php');
        require base_path('routes/api/v1/user/event-drafts.php');
        require base_path('routes/api/v1/user/event-occurrences.php');
        require base_path('routes/api/v1/user/event-earnings.php');
        require base_path('routes/api/v1/user/ticket-types.php');
        require base_path('routes/api/v1/user/tickets.php');
        require base_path('routes/api/v1/user/orders.php');
        require base_path('routes/api/v1/user/invitations.php');
        require base_path('routes/api/v1/user/prints.php');
        require base_path('routes/api/v1/user/discount-codes.php');
        require base_path('routes/api/v1/user/fundraisings.php');
        require base_path('routes/api/v1/user/fundraising-drafts.php');
        require base_path('routes/api/v1/user/fundraising-contributions.php');
        require base_path('routes/api/v1/user/fundraising-payment-intents.php');
        require base_path('routes/api/v1/user/fundraising-stats.php');
        require base_path('routes/api/v1/user/wallet.php');
        require base_path('routes/api/v1/user/subscriptions.php');
        require base_path('routes/api/v1/user/favorites.php');
        require base_path('routes/api/v1/user/notifications.php');
        require base_path('routes/api/v1/user/settings.php');
        require base_path('routes/api/v1/user/stats.php');
        require base_path('routes/api/v1/user/users.php');
        require base_path('routes/api/v1/user/order-intents.php');
    });

// /admin/*
Route::prefix('admin')->group(function () {
    require base_path('routes/api/admin/events.php');
    require base_path('routes/api/admin/fundraisings.php');
    require base_path('routes/api/admin/ticket-tags.php');
    require base_path('routes/api/admin/transferred-tickets.php');
    require base_path('routes/api/admin/tickets.php');
    require base_path('routes/api/admin/finance.php');
    require base_path('routes/api/admin/wallets.php');
    require base_path('routes/api/admin/reconciliation.php');
    require base_path('routes/api/admin/payment-report.php');
    require base_path('routes/api/admin/users.php');
    require base_path('routes/api/admin/customers.php');
    require base_path('routes/api/admin/customer-lookup.php');
    require base_path('routes/api/admin/distributors.php');
    require base_path('routes/api/admin/services.php');
    require base_path('routes/api/admin/oauth-clients.php');
    require base_path('routes/api/admin/invitations.php');
    require base_path('routes/api/admin/communications.php');
    require base_path('routes/api/admin/orders.php');
});
