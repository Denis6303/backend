<?php

namespace App\Http\Controllers\Api\V1\Partners;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class FinanceController extends Controller
{
    public function summary(): JsonResponse
    {
        return response()->json(['data' => []]);
    }
}

