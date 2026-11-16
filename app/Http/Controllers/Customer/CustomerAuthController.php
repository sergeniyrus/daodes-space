<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\CustomerOtpRateLimitException;
use App\Exceptions\SmsTransportException;
use App\Http\Controllers\Controller;
use App\Services\CustomerPhoneOtpService;
use App\Services\CustomerCartService;
use App\Support\EmployeePhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class CustomerAuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->intended(route('customer.organizations.index'));
        }

        return view('customer.auth.login');
    }

    /**
     * Вход клиента по телефону и постоянному паролю.
     */
    public function store(Request $request, CustomerCartService $cartService): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($request->only('phone'));
        }

        try {
            $phone = EmployeePhone::normalize($validator->validated()['phone']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['phone' => $exception->getMessage()])
                ->withInput($request->only('phone'));
        }

        $throttleKey = 'customer-login:' . hash('sha256', $phone . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'phone' => "Слишком много попыток входа. Повторите через {$seconds} сек.",
            ]);
        }

        $guard = Auth::guard('customer');

        if (!$guard->attempt([
            'phone' => $phone,
            'password' => $validator->validated()['password'],
        ])) {
            RateLimiter::hit($throttleKey, 900);
            throw ValidationException::withMessages([
                'phone' => 'Неверный номер телефона или пароль.',
            ]);
        }

        $customer = $guard->user();

        if (!$customer->phone_verified_at) {
            $guard->logout();
            RateLimiter::hit($throttleKey, 900);
            throw ValidationException::withMessages([
                'phone' => 'Сначала подтвердите номер телефона через SMS и установите пароль.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        // Переносим гостевую корзину в постоянную корзину клиента.
        $sessionCart = session(CustomerCartService::SESSION_KEY, []);
        $cartService->loadForOrganization(
            $customer,
            (int) ($sessionCart['organization_id'] ?? 0) ?: null
        );

        return redirect()->intended(route('customer.organizations.index'));
    }

    /**
     * Старые OTP-методы оставлены для обратной совместимости сервисного слоя.
     * HTTP-маршруты входа по OTP отключены: обычный вход теперь только по паролю.
     */
    public function requestCode(Request $request, CustomerPhoneOtpService $otp): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($request->only(['phone', 'name']));
        }

        try {
            $phone = EmployeePhone::normalize($validator->validated()['phone']);
            $otp->requestCode($phone, $validator->validated()['name'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['phone' => $exception->getMessage()])->withInput($request->only(['phone', 'name']));
        } catch (CustomerOtpRateLimitException $exception) {
            return back()->withErrors(['phone' => $exception->getMessage()])->withInput(['phone' => $phone ?? '']);
        } catch (SmsTransportException) {
            return back()->withErrors(['phone' => 'SMS-провайдер пока не подключён или временно недоступен.'])->withInput(['phone' => $phone ?? '']);
        }

        return back()->with('status', 'Код отправлен.')->withInput(['phone' => $phone]);
    }

    public function verifyCode(Request $request, CustomerPhoneOtpService $otp): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'max:32'],
            'code' => ['required', 'digits:6'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($request->except('code'));
        }

        try {
            $phone = EmployeePhone::normalize($validator->validated()['phone']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['phone' => $exception->getMessage()])->withInput($request->except('code'));
        }

        $customer = $otp->verifyCode($phone, $validator->validated()['code']);
        if (!$customer) {
            return back()->withErrors(['code' => 'Код неверен, истёк или уже использован.'])
                ->withInput($request->except('code'));
        }

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect()->intended(route('customer.organizations.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        // Постоянная корзина остаётся в аккаунте, а сессионная копия очищается.
        $request->session()->forget(CustomerCartService::SESSION_KEY);
        $request->session()->regenerateToken();

        return redirect()->route('customer.login')
            ->with('status', 'Вы вышли из клиентского кабинета.');
    }
}