<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\EmployeeInvitationService;
use App\Support\EmployeePhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class EmployeeInvitationController extends Controller
{
    public function create(): View
    {
        return view('employee.auth.activate');
    }

    public function store(
        Request $request,
        EmployeeInvitationService $invitations
    ): RedirectResponse {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:12', 'max:255', 'confirmed'],
        ]);

        try {
            $phone = EmployeePhone::normalize($validated['phone']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'phone' => $exception->getMessage(),
            ]);
        }

        $activated = $invitations->activate(
            $phone,
            $validated['code'],
            $validated['password']
        );

        if (!$activated) {
            throw ValidationException::withMessages([
                'code' => 'Код недействителен, истёк, уже использован или превышено число попыток. Запросите новое приглашение у менеджера.',
            ]);
        }

        return redirect()
            ->route('employee.login')
            ->with('status', 'Телефон подтверждён. Пароль установлен — теперь можно войти.');
    }
}
