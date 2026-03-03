<?php

use Illuminate\Support\Facades\Route;

Route::get('wallets', function () {
    return response()->json(['data' => []]);
});

