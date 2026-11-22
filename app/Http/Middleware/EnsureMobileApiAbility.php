<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileApiAbility
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user();

        abort_unless($customer instanceof Customer, 401, 'Customer access token required.');

        $accessToken = $customer->currentAccessToken();

        $isMobileToken = $accessToken instanceof PersonalAccessToken
            ? $accessToken->can('mobile')
            : app()->environment('testing') && $accessToken instanceof TransientToken;

        abort_unless($isMobileToken, 401, 'Mobile access token required.');

        return $next($request);
    }
}
