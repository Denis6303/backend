<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Category\CategoryController;

Route::get('categories', [CategoryController::class, 'index']);

