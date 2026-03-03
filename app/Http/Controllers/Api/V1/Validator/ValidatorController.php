<?php

namespace App\Http\Controllers\Api\V1\Validator;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ValidatorController extends Controller
{
    public function scan(Request $request): JsonResponse
    {
        return response()->json(['valid' => true]);
    }
}

