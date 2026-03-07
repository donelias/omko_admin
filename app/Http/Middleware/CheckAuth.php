<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class CheckAuth
{
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();

        if ($token) {
            $accessToken = PersonalAccessToken::with('tokenable')
                ->where('token', hash('sha256', $token))
                ->first();

            if ($accessToken && $accessToken->tokenable) {
                Auth::login($accessToken->tokenable);
            }
        }

        return $next($request);
    }
}
