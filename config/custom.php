<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Allowed countries
    |--------------------------------------------------------------------------
    |
    | Utilisé pour valider country_code durant la création d'événements (step2).
    | Clés = ISO 3166-1 alpha-2 (en minuscule).
    |
    */
    'allowed_countries' => [
        'tg' => 'Togo',
        'other' => 'Autre pays',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ticket minimum prices by currency
    |--------------------------------------------------------------------------
    */
    'ticket' => [
        'min_price' => [
            'XOF' => 500,
            'EUR' => 1,
            'USD' => 1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Order intents (ticket purchase flow)
    |--------------------------------------------------------------------------
    |
    | Paiement Togo : Yass (Mixx by Yass) et Flooz (Moov) — gateways dédiées
    | (YassGateway, FloozGateway) basées sur FakeGateway jusqu’aux APIs agrégateur.
    | Quand les APIs réelles seront disponibles : implémenter createCheckoutForOrderIntent
    | / verifyOrderIntentPayment dans ces classes et conserver les codes `yass` / `flooz`.
    |
    */
    'order' => [
        'expiration_minutes' => (int) env('ORDER_INTENT_EXPIRATION_MINUTES', 15),
        'max_tickets_per_type_online' => (int) env('ORDER_INTENT_MAX_TICKETS_PER_TYPE', 10),
    ],
];

