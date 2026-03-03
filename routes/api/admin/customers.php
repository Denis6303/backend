<?php

use Illuminate\Support\Facades\Route;

Route::get('customers', function () {
    return response()->json(['data' => []]);
});

