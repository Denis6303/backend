<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Point d'entrée unique pour l'API.
| - /api/v1/*     : API versionnée (routes/api/v1.php)
| - /api/admin/*  : API admin (routes/api/admin.php)
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

// /api/v1/*
Route::prefix('{version}')
    ->whereIn('version', ['v1'])
    ->group(base_path('routes/api/v1.php'));

// /api/admin/*
Route::prefix('admin')->group(base_path('routes/api/admin.php'));
