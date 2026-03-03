<?php

use Illuminate\Support\Facades\Route;

Route::get('payment-report', function () {
    return response()->json(['data' => []]);
});

