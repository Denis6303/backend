<?php

/*
|--------------------------------------------------------------------------
| API Admin Routes
|--------------------------------------------------------------------------
|
| Agrégateur des routes API admin.
| Préfixe : /api/admin/*
|
*/

require base_path('routes/api/admin/events.php');
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
