<?php

use Illuminate\Support\Facades\Route;

Route::get('customer-lookup', function () {
    return response()->json(['data' => []]);
});

