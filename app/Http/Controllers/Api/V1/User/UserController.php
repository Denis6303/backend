<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group User authentication
 *
 * Endpoints related to the authenticated user.
 */
class UserController extends Controller
{
    /**
     * My profile (current user).
     *
     * Returns the authenticated user's information associated with the Bearer token.
     *
     * @authenticated
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "email": "user@example.com"
     *   }
     * }
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()]);
    }
}

