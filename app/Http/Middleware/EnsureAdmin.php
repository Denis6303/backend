<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdmin
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || ! ((bool) ($user->is_admin ?? false))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}

