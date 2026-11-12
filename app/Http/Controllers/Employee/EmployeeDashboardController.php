<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmployeeDashboardController extends Controller
{
    public function index(): View
    {
        $employee = Auth::guard('employee')->user();

        $memberships = $employee->activeMemberships()
            ->with('organization:id,name,status')
            ->orderBy('id')
            ->get();

        return view('employee.dashboard', [
            'employee' => $employee,
            'memberships' => $memberships,
        ]);
    }
}