<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            abort(401, 'Необходима авторизация.');
        }

        if ((int) auth()->user()->access_level !== 3) {
            abort(403, 'Доступ запрещен — только для администраторов.');
        }

        return $next($request);
    }
}
