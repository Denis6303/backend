<?php

use Illuminate\Support\Facades\Route;

Route::get('transferred-tickets', function () {
    return response()->json(['data' => []]);
});

