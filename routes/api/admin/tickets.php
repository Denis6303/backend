<?php

use Illuminate\Support\Facades\Route;

Route::get('tickets', function () {
    return response()->json(['data' => []]);
});

