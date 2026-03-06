<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * @group User authentication
 *
 * Réinitialisation du mot de passe.
 */
class PasswordResetController extends Controller
{
    /**
     * Request password reset.
     *
     * Envoie un email avec un lien de réinitialisation.
     *
     * @bodyParam email string required Email. Example: user@example.com
     *
     * @response 200 { "message": "We have emailed your password reset link." }
     */
    public function sendResetLinkEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => __($status),
            ], 400);
        }

        return response()->json([
            'message' => __($status),
        ]);
    }

    /**
     * Redirect reset link.
     *
     * Endpoint backend appelé depuis l'email; redirige vers le frontend si `FRONTEND_URL` est défini.
     * Sinon retourne JSON `{ token, email }`.
     *
     * @urlParam token string required Reset token. Example: abc123
     * @queryParam email string required Email. Example: user@example.com
     */
    public function redirectToFrontend(Request $request, string $token): JsonResponse|RedirectResponse
    {
        $email = $request->query('email');
        $frontendUrl = env('FRONTEND_URL');

        if ($frontendUrl) {
            $query = http_build_query([
                'token' => $token,
                'email' => $email,
            ]);

            $url = rtrim($frontendUrl, '/').'/reset-password?'.$query;

            return redirect()->away($url);
        }

        return response()->json([
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * Reset password.
     *
     * Applique le nouveau mot de passe à partir du token.
     *
     * @bodyParam token string required Reset token. Example: abc123
     * @bodyParam email string required Email. Example: user@example.com
     * @bodyParam password string required Nouveau mot de passe. Example: NewPass123
     * @bodyParam password_confirmation string required Confirmation. Example: NewPass123
     *
     * @response 200 { "message": "Your password has been reset." }
     */
    public function reset(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $credentials,
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __($status),
            ], 400);
        }

        return response()->json([
            'message' => __($status),
        ]);
    }
}

