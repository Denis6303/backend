<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\TokenRepository;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

/**
 * Accepts any valid Passport access token (password / personal / client credentials).
 * Authenticates the user when oauth_user_id is present on the token.
 */
class UserOrClient
{
    public function __construct(
        protected ResourceServer $server,
        protected TokenRepository $tokens
    ) {
    }

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        if (! $request->bearerToken()) {
            throw new AuthenticationException(__('auth.unauthenticated'));
        }

        $psr = (new PsrHttpFactory(
            new Psr17Factory,
            new Psr17Factory,
            new Psr17Factory,
            new Psr17Factory
        ))->createRequest($request);

        try {
            $psr = $this->server->validateAuthenticatedRequest($psr);
        } catch (OAuthServerException) {
            throw new AuthenticationException(__('auth.unauthenticated'));
        }

        $tokenId = $psr->getAttribute('oauth_access_token_id');
        $token = $this->tokens->find($tokenId);

        if (! $token || $token->revoked) {
            throw new AuthenticationException(__('auth.unauthenticated'));
        }

        $userId = $psr->getAttribute('oauth_user_id');
        if ($userId) {
            $user = User::query()->find($userId);
            if ($user) {
                Auth::guard('api')->setUser($user->withAccessToken($token));
            }
        }

        return $next($request);
    }
}
