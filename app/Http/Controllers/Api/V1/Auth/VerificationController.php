<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * @group User authentication
 *
 * Vérification de l'email (liens signés) et renvoi de lien.
 */
class VerificationController extends Controller
{
    /**
     * Verify email.
     *
     * Marque l'email comme vérifié à partir d'un lien signé (middleware `signed` sur la route).
     * Optionnel: redirige vers le frontend si `FRONTEND_URL` est défini.
     *
     * @urlParam id integer required ID user. Example: 1
     * @urlParam hash string required SHA1(email). Example: 6b1b36cbb04b41490bfc0ab2bfa26f86a4f37e58
     *
     * @response 200 { "message": "Email verified", "user": { "id": 1, "email": "user@example.com" } }
     */
    public function verify(Request $request, int $id, string $hash): JsonResponse|RedirectResponse
    {
        /** @var User|null $user */
        $user = User::query()->find($id);

        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->email_verified_at) {
            return $this->respondVerified($user);
        }

        $emailForVerification = method_exists($user, 'getEmailForVerification')
            ? $user->getEmailForVerification()
            : $user->email;

        if (! hash_equals(sha1($emailForVerification), $hash)) {
            return response()->json(['message' => 'Invalid verification link'], 403);
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        event(new Verified($user));

        return $this->respondVerified($user);
    }

    /**
     * Resend verification email.
     *
     * Renvoie un email de vérification si l'utilisateur est authentifié et non vérifié.
     *
     * @response 200 { "message": "Verification link sent" }
     *
     * @authenticated
     */
    public function resend(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email already verified'], 400);
        }

        if (method_exists($user, 'sendEmailVerificationNotification')) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['message' => 'Verification link sent']);
    }

    private function respondVerified(User $user): JsonResponse|RedirectResponse
    {
        $frontendUrl = env('FRONTEND_URL');

        if ($frontendUrl) {
            $url = rtrim($frontendUrl, '/').'/email-verified';

            return redirect()->away($url);
        }

        return response()->json([
            'message' => 'Email verified',
            'user' => $user,
        ]);
    }
}

