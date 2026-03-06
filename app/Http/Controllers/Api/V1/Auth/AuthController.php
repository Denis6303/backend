<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * @group User authentication
 *
 * Authentification utilisateur (Passport Bearer token).
 *
 * Ce groupe couvre:
 * - Inscription (création de compte)
 * - Connexion (retourne un Bearer token)
 * - Déconnexion (révoque le token courant)
 */
class AuthController extends Controller
{
    /**
     * Register a new user.
     *
     * Crée un utilisateur avec uniquement email et mot de passe obligatoires,
     * puis renvoie un token (Bearer). Déclenche aussi l'email de vérification.
     *
     * @bodyParam email string required Email. Example: user@example.com
     * @bodyParam password string required Mot de passe (min 8). Example: SecretPass123
     * @bodyParam first_name string optional Prénom. Example: John
     * @bodyParam last_name string optional Nom. Example: Doe
     * @bodyParam phone string optional Numéro de téléphone. Example: +33123456789
     * @bodyParam city string optional Ville. Example: Paris
     * @bodyParam address string optional Adresse. Example: 123 Rue Lafayette
     * @bodyParam birthday date optional Date de naissance (YYYY-MM-DD). Example: 1998-05-03
     * @bodyParam company_name string optional Nom de l'entreprise. Example: Acme Corp
     * @bodyParam fcm_token string optional Jeton FCM. Example: eQ8XsH7VQ9KnZCwJ6b4Ayz
     * @bodyParam device_id string optional Identifiant unique de l'appareil. Example: 123e4567-e89b-12d3-a456-426614174000
     * @bodyParam platform string optional Plateforme de l'appareil. Example: ios
     * @bodyParam app_version string optional Version de l'application. Example: 1.2.3
     * @bodyParam os_version string optional Version de l'OS. Example: iOS 15.0
     * @bodyParam device_model string optional Modèle de l'appareil. Example: iPhone 13
     * @bodyParam permission_status string optional Statut des permissions. Example: authorized
     *
     * @response 201 {
     *   "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
     *   "user": {
     *     "id": 1,
     *     "email": "user@example.com"
     *   }
     * }
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'birthday' => ['nullable', 'date'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'fcm_token' => ['nullable', 'string', 'max:255'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'os_version' => ['nullable', 'string', 'max:50'],
            'device_model' => ['nullable', 'string', 'max:100'],
            'permission_status' => ['nullable', 'string', 'max:50'],
        ]);

        $user = User::create([
            // Le nom complet sera complété plus tard dans le profil.
            'name' => $data['email'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        if (method_exists($user, 'sendEmailVerificationNotification')) {
            $user->sendEmailVerificationNotification();
        }

        $token = $user->createToken('api')->accessToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ], 201);
    }

    /**
     * Login.
     *
     * Authentifie un utilisateur et retourne un token Bearer Passport.
     *
     * @bodyParam email string required Email. Example: user@example.com
     * @bodyParam password string required Mot de passe. Example: SecretPass123
     *
     * @response 200 {
     *   "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
     *   "user": { "id": 1, "email": "user@example.com" }
     * }
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('api')->accessToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Logout.
     *
     * Révoque le token courant.
     *
     * @response 200 { "message": "Logged out" }
     *
     * @authenticated
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->token();
        if ($token) {
            $token->revoke();
        }

        return response()->json(['message' => 'Logged out']);
    }
}

