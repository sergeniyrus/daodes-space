<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileApiAbility
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user() instanceof Customer,
            401,
            'Customer access token required.'
        );

        return $next($request);
    }
}
