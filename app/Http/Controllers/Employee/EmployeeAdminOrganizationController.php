<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationEmployeeMembership;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmployeeAdminOrganizationController extends Controller
{
    public function manage(Organization $organization): View
    {
        $employee = Auth::guard('employee')->user();
        abort_unless($employee, 403);
        OrganizationEmployeeMembership::query()
            ->where('organization_id', $organization->id)
            ->where('employee_id', $employee->id)
            ->where('role', 'admin')
            ->where('status', 'active')
            ->firstOrFail();

        return view('employee.admin.manage', [
            'organization' => $organization,
            'orderSettings' => $organization->orderSettings,
            'tvScreens' => range(1, max(1, (int) ($organization->tv_screens_count ?? 3))),
            'tvScreensConfig' => $organization->tv_screens_config ?? [],
        ]);
    }
}
