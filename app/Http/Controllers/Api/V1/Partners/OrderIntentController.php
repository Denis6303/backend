<?php

namespace App\Http\Controllers\Api\V1\Partners;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderIntentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        return response()->json(['data' => []], 201);
    }
}

