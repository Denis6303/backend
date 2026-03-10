<?php

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
|
| Agrégateur des routes API version 1.
| - API publique : /api/v1/*
| - API utilisateur : /api/v1/user/*
|
*/

// API publique
require base_path('routes/api/v1/auth.php');
require base_path('routes/api/v1/passport.php');
require base_path('routes/api/v1/events.php');
require base_path('routes/api/v1/order-intents.php');
require base_path('routes/api/v1/payment-methods.php');
require base_path('routes/api/v1/users.php');
require base_path('routes/api/v1/open.php');
require base_path('routes/api/v1/categories.php');
require base_path('routes/api/v1/discount-codes.php');
require base_path('routes/api/v1/distributors.php');
require base_path('routes/api/v1/partners.php');
require base_path('routes/api/v1/webhooks.php');
require base_path('routes/api/v1/validator.php');

// API utilisateur connecté
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
require base_path('routes/api/v1/user/subscriptions.php');
require base_path('routes/api/v1/user/favorites.php');
require base_path('routes/api/v1/user/notifications.php');
require base_path('routes/api/v1/user/settings.php');
require base_path('routes/api/v1/user/stats.php');
require base_path('routes/api/v1/user/users.php');
require base_path('routes/api/v1/user/order-intents.php');
