<?php

use Illuminate\Support\Facades\Route;

Route::get('oauth-clients', function () {
    return response()->json(['data' => []]);
});

