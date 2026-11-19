<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateEmployee
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $guard = Auth::guard('employee');

        if (!$guard->check()) {
            return redirect()->guest(
                route('employee.login')
            );
        }

        $employee = $guard->user();

        /*
         * Повторно проверяем подтверждение телефона и членство.
         * Это закрывает доступ сразу после блокировки сотрудника
         * или отзыва последнего активного членства.
         */
        if (
            !$employee->phone_verified_at ||
            !$employee->activeMemberships()->exists()
        ) {
            $guard->logout();
            // Rotate the session ID without destroying the separate web guard.
            $request->session()->regenerate();

            return redirect()
                ->route('employee.login')
                ->withErrors([
                    'phone' => 'Доступ к рабочему кабинету приостановлен. Обратитесь к менеджеру.',
                ]);
        }

        if (
            !$employee->last_seen_at ||
            $employee->last_seen_at->lt(now()->subSeconds(30))
        ) {
            $employee->forceFill([
                'last_seen_at' => now(),
            ])->saveQuietly();
        }

        return $next($request);
    }
}