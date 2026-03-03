<?php

namespace App\Http\Controllers\Api\V1\DiscountCode;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscountCodeController extends Controller
{
    public function validateCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $code = DiscountCode::query()->where('code', $data['code'])->first();

        if (! $code || ! $code->isActive()) {
            return response()->json(['valid' => false], 404);
        }

        return response()->json([
            'valid' => true,
            'discount' => $code->discount,
        ]);
    }
}

