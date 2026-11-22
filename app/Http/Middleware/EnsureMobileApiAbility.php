<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileApiAbility
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user();

        abort_unless(
            $customer && method_exists($customer, 'tokenCan') && $customer->tokenCan('mobile'),
            401,
            'Mobile access token required.'
        );

        return $next($request);
    }
}
