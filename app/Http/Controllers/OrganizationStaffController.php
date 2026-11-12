<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationEmployee;
use App\Models\OrganizationEmployeeMembership;
use App\Models\EmployeePhoneChallenge;
use App\Support\EmployeePhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

class OrganizationStaffController extends Controller
{
    /**
     * Добавляет сотрудника в конкретную организацию.
     *
     * Учётная запись создаётся один раз на телефон.
     * Если сотрудник уже существует, используется его текущая запись.
     * Новое членство остаётся pending до подтверждения телефона.
     */
    public function store(
        Request $request,
        Organization $organization
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:32'],
            'role' => ['required', 'in:employee,cook,courier'],
        ]);

        try {
            $phone = EmployeePhone::normalize($validated['phone']);
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withErrors(['phone' => $exception->getMessage()])
                ->withInput($request->except('password'));
        }

        $result = DB::transaction(function () use ($validated, $phone, $organization) {
            $employee = OrganizationEmployee::query()
                ->where('phone', $phone)
                ->lockForUpdate()
                ->first();

            if (!$employee) {
                $employee = OrganizationEmployee::create([
                    'name' => $validated['name'],
                    'phone' => $phone,
                    // До прохождения собственного процесса активации
                    // пароль неизвестен менеджеру и не может быть использован.
                    'password' => Str::random(64),
                ]);
            }

            $membership = OrganizationEmployeeMembership::query()
                ->where('organization_id', $organization->id)
                ->where('employee_id', $employee->id)
                ->lockForUpdate()
                ->first();

            if ($membership) {
                return [
                    'created' => false,
                    'employee' => $employee,
                ];
            }

            $organization->employeeMemberships()->create([
                'employee_id' => $employee->id,
                'role' => $validated['role'],
                'status' => 'pending',
            ]);

            return [
                'created' => true,
                'employee' => $employee,
            ];
        });

        if (!$result['created']) {
            return redirect()
                ->route('organizations.manage', $organization)
                ->with('error', 'Этот телефон уже добавлен в персонал организации.');
        }

        $message = $result['employee']->phone_verified_at
            ? 'Сотрудник добавлен. Его доступ ожидает активации менеджером.'
            : 'Сотрудник добавлен. Доступ ожидает подтверждения телефона и активации.';

        return redirect()
            ->route('organizations.manage', $organization)
            ->with('success', $message);
    }

    /**
     * Изменяет роль сотрудника только в текущей организации.
     */
    public function updateRole(
        Request $request,
        Organization $organization,
        OrganizationEmployee $employee
    ): RedirectResponse {
        $validated = $request->validate([
            'role' => ['required', 'in:employee,cook,courier'],
        ]);

        $membership = $this->membership($organization, $employee);

        $membership->update([
            'role' => $validated['role'],
        ]);

        return redirect()
            ->route('organizations.manage', $organization)
            ->with('success', 'Роль сотрудника обновлена.');
    }

    /**
     * Меняет статус членства, не затрагивая другие организации.
     */
    public function updateStatus(
        Request $request,
        Organization $organization,
        OrganizationEmployee $employee
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,active,blocked'],
        ]);

        $membership = $this->membership($organization, $employee);

        if (
            $validated['status'] === 'active' &&
            !$employee->phone_verified_at
        ) {
            return redirect()
                ->route('organizations.manage', $organization)
                ->with('error', 'Нельзя активировать доступ до подтверждения телефона сотрудника.');
        }

        $membership->update([
            'status' => $validated['status'],
        ]);

        if ($validated['status'] === 'blocked') {
            EmployeePhoneChallenge::query()
                ->where('employee_id', $employee->id)
                ->where('organization_id', $organization->id)
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        }

        return redirect()
            ->route('organizations.manage', $organization)
            ->with('success', 'Статус доступа сотрудника обновлён.');
    }

    /**
     * Удаляет только членство в выбранной организации.
     * Общая учётная запись и другие организации сохраняются.
     */
    public function destroy(
        Organization $organization,
        OrganizationEmployee $employee
    ): RedirectResponse {
        $membership = $this->membership($organization, $employee);

        EmployeePhoneChallenge::query()
            ->where('employee_id', $employee->id)
            ->where('organization_id', $organization->id)
            ->whereNull('consumed_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $membership->delete();

        return redirect()
            ->route('organizations.manage', $organization)
            ->with('success', 'Сотрудник удалён из персонала этой организации.');
    }

    private function membership(
        Organization $organization,
        OrganizationEmployee $employee
    ): OrganizationEmployeeMembership {
        return OrganizationEmployeeMembership::query()
            ->where('organization_id', $organization->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();
    }
}
