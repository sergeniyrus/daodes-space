<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmployeeRoleMiddleware
{
    /**
     * Проверяет роль сотрудника в уже проверенной организации.
     *
     * Пример: employee.role:cook или employee.role:cook,courier
     */
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles
    ): Response {
        $membership = $request->attributes->get(
            'employee_membership'
        );

        abort_unless(
            $membership &&
            $membership->status === 'active' &&
            $membership->hasRole($roles),
            403
        );

        return $next($request);
    }
}