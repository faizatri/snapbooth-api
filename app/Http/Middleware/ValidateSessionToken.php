<?php

namespace App\Http\Middleware;

use App\Models\Session;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateSessionToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->route('session_token');

        $session = Session::where('session_token', $token)
            ->with('event')
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired session token',
                'data'    => null,
                'errors'  => null,
            ], 401);
        }

        $request->attributes->set('boothSession', $session);

        return $next($request);
    }
}
