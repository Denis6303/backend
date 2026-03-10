<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginTicket;
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
    private const LOCALE = 'fr';

    /**
     * Register a new user.
     *
     * Body minimal : email + password. Champs optionnels : first_name, last_name, etc.
     * Après inscription, un email de vérification est envoyé.
     *
     * @bodyParam email string required Email. Example: user@example.com
     * @bodyParam password string required Mot de passe (min 8). Example: SecretPass123
     * @bodyParam first_name string optional Prénom.
     * @bodyParam last_name string optional Nom.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::create([
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
        ]);

        if (method_exists($user, 'sendEmailVerificationNotification')) {
            $user->sendEmailVerificationNotification();
        }

        $tokenResult = $user->createToken('api');
        $dataResponse = $this->buildAuthData($tokenResult, $user);

        return response()->json([
            'success' => true,
            'code' => 0,
            'locale' => self::LOCALE,
            'message' => 'Inscription réussie. Un lien de vérification vous a été envoyé sur cet email. Cliquez dessus pour vérifier votre compte.',
            'data' => $dataResponse,
        ], 201);
    }

    /**
     * Login.
     *
     * Body : email + password uniquement.
     *
     * @bodyParam email string required Email. Example: user@example.com
     * @bodyParam password string required Mot de passe. Example: SecretPass123
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
            return response()->json([
                'success' => false,
                'code' => 401,
                'locale' => self::LOCALE,
                'message' => 'Identifiants incorrects',
                'data' => null,
            ], 401);
        }

        $tokenResult = $user->createToken('api');
        $dataResponse = $this->buildAuthData($tokenResult, $user);

        return response()->json([
            'success' => true,
            'code' => 0,
            'locale' => self::LOCALE,
            'message' => 'Connexion réussie',
            'data' => $dataResponse,
        ]);
    }

    /**
     * Logout.
     *
     * @authenticated
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->token();
        if ($token) {
            $token->revoke();
        }

        return response()->json([
            'success' => true,
            'code' => 0,
            'locale' => self::LOCALE,
            'message' => 'Déconnexion réussie',
            'data' => null,
        ]);
    }

    /**
     * Exchange a login_ticket (one-time) for an access token.
     *
     * Utilisé après clic sur le lien de vérification d'email depuis le frontend.
     *
     * @bodyParam login_ticket string required Ticket unique reçu en query string. Example: 8d9e0a1b-2f6b-4b9b-8f4b-2c9c1f6a1b2c
     */
    public function exchangeTicket(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login_ticket' => ['required', 'string'],
        ]);

        $ticket = LoginTicket::query()
            ->where('ticket', $data['login_ticket'])
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $ticket) {
            return response()->json([
                'success' => false,
                'code' => 400,
                'locale' => self::LOCALE,
                'message' => 'Login ticket invalide ou expiré',
                'data' => null,
            ], 400);
        }

        /** @var User $user */
        $user = $ticket->user;

        if (! $user) {
            return response()->json([
                'success' => false,
                'code' => 404,
                'locale' => self::LOCALE,
                'message' => 'Utilisateur introuvable pour ce ticket',
                'data' => null,
            ], 404);
        }

        // Marque le ticket comme utilisé pour empêcher toute réutilisation.
        $ticket->forceFill(['used_at' => now()])->save();

        $tokenResult = $user->createToken('api');
        $dataResponse = $this->buildAuthData($tokenResult, $user);

        return response()->json([
            'success' => true,
            'code' => 0,
            'locale' => self::LOCALE,
            'message' => 'Connexion réussie',
            'data' => $dataResponse,
        ]);
    }

    /**
     * @param  object  $tokenResult  Résultat de createToken('api') : accessToken, token (id, expires_at)
     * @return array{id: string, access_token: string, access_expires_at: string|null, user: array}
     */
    private function buildAuthData(object $tokenResult, User $user): array
    {
        $accessToken = $tokenResult->accessToken;
        $token = $tokenResult->token ?? null;
        $id = $token?->id ?? '';
        $expiresAt = $token?->expires_at ?? null;
        $expiresAtFormatted = $expiresAt && method_exists($expiresAt, 'format')
            ? $expiresAt->format('Y-m-d H:i:s')
            : null;

        return [
            'id' => (string) $id,
            'access_token' => $accessToken,
            'access_expires_at' => $expiresAtFormatted,
            'user' => $this->userToArray($user),
        ];
    }

    /**
     * Retourne uniquement les champs présents en base (users).
     */
    private function userToArray(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'is_admin' => (bool) $user->is_admin,
            'created_at' => $user->created_at->toIso8601String(),
            'updated_at' => $user->updated_at->toIso8601String(),
        ];
    }
}

