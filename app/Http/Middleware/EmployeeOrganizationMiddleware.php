<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EmployeeOrganizationMiddleware
{
    /**
     * Проверяет активное членство сотрудника в организации.
     *
     * Сотрудник не получает доступ к организации только потому,
     * что успешно вошёл в систему: членство проверяется на каждом запросе.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $employee = Auth::guard('employee')->user();
        $organization = $request->route('organization');

        abort_unless(
            $employee &&
            $organization instanceof Organization &&
            $organization->isActive(),
            403
        );

        $membership = $employee->memberships()
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->whereHas('organization', function ($query) {
                $query->where('status', 'active');
            })
            ->first();

        abort_unless($membership, 403);

        $request->attributes->set(
            'employee_membership',
            $membership
        );

        return $next($request);
    }
}