<?php

namespace App\Http\Controllers\Api\V1\Open;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class OpenController extends Controller
{
    public function ping(): JsonResponse
    {
        return response()->json(['pong' => true]);
    }
}

