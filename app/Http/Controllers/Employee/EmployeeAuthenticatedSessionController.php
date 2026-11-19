<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Support\EmployeePhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class EmployeeAuthenticatedSessionController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::guard('employee')->check()) {
            return redirect()->route('employee.dashboard');
        }

        return view('employee.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        if (Auth::guard('employee')->check()) {
            return redirect()->route('employee.dashboard');
        }

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        try {
            $phone = EmployeePhone::normalize($validated['phone']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['phone' => $exception->getMessage()]);
        }

        $throttleKey = 'employee-login:' . hash('sha256', Str::lower($phone) . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'phone' => "Слишком много попыток. Повторите через {$seconds} сек.",
            ]);
        }

        $guard = Auth::guard('employee');
        $authenticated = $guard->attempt([
            'phone' => $phone,
            'password' => $validated['password'],
        ]);

        if (!$authenticated) {
            RateLimiter::hit($throttleKey, 900);
            throw ValidationException::withMessages([
                'phone' => 'Не удалось войти. Проверьте телефон и пароль.',
            ]);
        }

        $employee = $guard->user();

        if (!$employee->phone_verified_at || !$employee->activeMemberships()->exists()) {
            $guard->logout();
            RateLimiter::hit($throttleKey, 900);
            throw ValidationException::withMessages([
                'phone' => 'Учётная запись не подтверждена или не имеет активного доступа к организации.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $employee->forceFill(['last_seen_at' => now()])->saveQuietly();

        return redirect()->intended(route('employee.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('employee')->logout();
        $request->session()->regenerate();

        return redirect()->route('employee.login')
            ->with('status', 'Вы вышли из рабочего кабинета.');
    }
}