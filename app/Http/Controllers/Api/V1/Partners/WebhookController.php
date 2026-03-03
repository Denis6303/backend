<?php

namespace App\Http\Controllers\Api\V1\Partners;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function handle(string $provider, Request $request): JsonResponse
    {
        return response()->json(['provider' => $provider, 'received' => true]);
    }
}

