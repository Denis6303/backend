<?php

use Illuminate\Support\Facades\Route;

Route::get('services', function () {
    return response()->json(['data' => []]);
});

