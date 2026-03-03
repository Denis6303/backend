<?php

namespace App\Http\Controllers\Api\V1\Partners;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class EventController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => []]);
    }
}

