<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Simulate successful payment verification
    |--------------------------------------------------------------------------
    |
    | When true, verifyOrderIntentPayment() succeeds for all gateways that use
    | FakeGateway (including Yass/Flooz stubs until the real PSP is integrated).
    |
    | Use only for short-lived end-to-end tests on a live server. Set to false
    | as soon as real payment verification is in place. Every success is logged
    | as a warning with the order intent id and key.
    |
    */
    'simulate_successful_payment_verify' => env('PAYMENTS_SIMULATE_SUCCESSFUL_VERIFY', false),

    /*
    |--------------------------------------------------------------------------
    | Secret for order-intent:confirm-for-testing
    |--------------------------------------------------------------------------
    |
    | Random string (e.g. php -r "echo bin2hex(random_bytes(16));"). Required
    | to run the Artisan command that confirms a single stuck intent.
    |
    */
    'test_confirm_secret' => env('ORDER_INTENT_TEST_CONFIRM_SECRET', ''),

];
