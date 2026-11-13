<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationEmployee;
use App\Models\OrganizationEmployeeMembership;
use App\Models\EmployeePhoneChallenge;
use App\Exceptions\EmployeeInvitationRateLimitException;
use App\Exceptions\SmsTransportException;
use App\Services\EmployeeInvitationService;
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
        Organization $organization,
        EmployeeInvitationService $invitations
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
            // Уникальный индекс phone гарантирует одну учётную запись на номер.
            // firstOrCreate обрабатывает конкурентную вставку того же телефона.
            $employee = OrganizationEmployee::query()->firstOrCreate(
                ['phone' => $phone],
                [
                    'name' => $validated['name'],
                    // Менеджер не знает пароль сотрудника и не может им войти.
                    'password' => Str::random(64),
                ]
            );

            $employee = OrganizationEmployee::query()
                ->whereKey($employee->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Уникальный индекс пары организация/сотрудник не позволяет
            // создать дублирующее членство при параллельном приглашении.
            $membership = OrganizationEmployeeMembership::query()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'employee_id' => $employee->id,
                ],
                [
                    'role' => $validated['role'],
                    'status' => 'pending',
                ]
            );

            return [
                'created' => $membership->wasRecentlyCreated,
                'employee' => $employee,
            ];
        });

        if (!$result['created']) {
            return redirect()
                ->route('organizations.manage', $organization)
                ->with('error', 'Этот телефон уже добавлен в персонал организации.');
        }

        if ($result['employee']->phone_verified_at) {
            return redirect()
                ->route('organizations.manage', $organization)
                ->with('success', 'Сотрудник добавлен. Его доступ ожидает активации менеджером.');
        }

        try {
            $invitations->sendInvitation(
                (int) $result['employee']->id,
                (int) $organization->id
            );

            return redirect()
                ->route('organizations.manage', $organization)
                ->with('success', 'Сотрудник добавлен. SMS-приглашение передано провайдеру.');
        } catch (SmsTransportException | EmployeeInvitationRateLimitException $exception) {
            $message = $exception instanceof EmployeeInvitationRateLimitException
                ? $exception->getMessage()
                : 'SMS-провайдер не подключён или отправка временно недоступна. Сотрудник сохранён, приглашение можно отправить повторно.';

            return redirect()
                ->route('organizations.manage', $organization)
                ->with('error', 'Сотрудник добавлен, но SMS не отправлено: ' . $message);
        }
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

        DB::transaction(function () use ($membership, $employee, $organization, $validated) {
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
        });

        return redirect()
            ->route('organizations.manage', $organization)
            ->with('success', 'Статус доступа сотрудника обновлён.');
    }

    /**
     * Повторно отправляет приглашение только для членства текущей организации.
     */
    public function resendInvitation(
        Organization $organization,
        OrganizationEmployee $employee,
        EmployeeInvitationService $invitations
    ): RedirectResponse {
        $membership = $this->membership($organization, $employee);

        if ($membership->status !== 'pending') {
            return redirect()
                ->route('organizations.manage', $organization)
                ->with('error', 'Повторное приглашение доступно только для ожидающего членства.');
        }

        if ($employee->phone_verified_at) {
            return redirect()
                ->route('organizations.manage', $organization)
                ->with('success', 'Телефон уже подтверждён. Менеджер может активировать членство.');
        }

        try {
            $invitations->sendInvitation(
                (int) $employee->id,
                (int) $organization->id
            );

            return redirect()
                ->route('organizations.manage', $organization)
                ->with('success', 'Повторное SMS-приглашение передано провайдеру.');
        } catch (SmsTransportException | EmployeeInvitationRateLimitException $exception) {
            $message = $exception instanceof EmployeeInvitationRateLimitException
                ? $exception->getMessage()
                : 'SMS-провайдер не подключён или отправка временно недоступна.';

            return redirect()
                ->route('organizations.manage', $organization)
                ->with('error', 'SMS не отправлено: ' . $message);
        }
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

        DB::transaction(function () use ($membership, $employee, $organization) {
            EmployeePhoneChallenge::query()
                ->where('employee_id', $employee->id)
                ->where('organization_id', $organization->id)
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $membership->delete();
        });

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
