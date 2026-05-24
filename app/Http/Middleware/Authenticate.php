<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    // API-only — tidak ada redirect ke login, selalu throw AuthenticationException
    protected function redirectTo(Request $request): ?string
    {
        return null;
    }
}
