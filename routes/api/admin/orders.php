<?php

use Illuminate\Support\Facades\Route;

Route::get('orders', function () {
    return response()->json(['data' => []]);
});

