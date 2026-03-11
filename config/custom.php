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
            'XOF' => 1000,
            'EUR' => 1,
            'USD' => 1,
        ],
    ],
];

