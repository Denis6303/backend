<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group User authentication
 *
 * Endpoints liés à l'utilisateur connecté.
 */
class UserController extends Controller
{
    /**
     * Mon profil (user courant).
     *
     * Retourne les informations de l'utilisateur authentifié associé au token Bearer.
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

