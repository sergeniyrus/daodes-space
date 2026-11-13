<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\CustomerOtpRateLimitException;
use App\Exceptions\SmsTransportException;
use App\Http\Controllers\Controller;
use App\Services\CustomerPhoneOtpService;
use App\Support\EmployeePhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use InvalidArgumentException;

class CustomerAuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->intended(
                route('customer.organizations.index')
            );
        }

        return view('customer.auth.login');
    }

    /**
     * Запрос одноразового кода входа.
     */
    public function requestCode(
        Request $request,
        CustomerPhoneOtpService $otp
    ): RedirectResponse {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput($request->only(['phone', 'name']));
        }

        try {
            $phone = EmployeePhone::normalize($validator->validated()['phone']);
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withErrors(['phone' => $exception->getMessage()])
                ->withInput($request->only(['phone', 'name']));
        }

        try {
            $otp->requestCode($phone, $validator->validated()['name'] ?? null);
        } catch (CustomerOtpRateLimitException $exception) {
            return back()
                ->withErrors(['phone' => $exception->getMessage()])
                ->withInput(['phone' => $phone]);
        } catch (SmsTransportException) {
            return back()
                ->withErrors([
                    'phone' => 'SMS-провайдер пока не подключён или временно недоступен. Код не отправлен.',
                ])
                ->withInput(['phone' => $phone]);
        }

        return back()
            ->with('status', 'Код отправлен. Введите его ниже.')
            ->withInput(['phone' => $phone]);
    }

    /**
     * Проверка кода и создание отдельной customer-сессии.
     */
    public function verifyCode(
        Request $request,
        CustomerPhoneOtpService $otp
    ): RedirectResponse {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'max:32'],
            'code' => ['required', 'digits:6'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput($request->except('code'));
        }

        try {
            $phone = EmployeePhone::normalize($validator->validated()['phone']);
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withErrors(['phone' => $exception->getMessage()])
                ->withInput($request->except('code'));
        }

        $customer = $otp->verifyCode(
            $phone,
            $validator->validated()['code']
        );

        if (!$customer) {
            return back()
                ->withErrors([
                    'code' => 'Код неверен, истёк или уже использован. Запросите новый код.',
                ])
                ->withInput($request->except('code'));
        }

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect()->intended(
            route('customer.organizations.index')
        );
    }

    /**
     * Завершает только клиентскую сессию, не затрагивая web и employee.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->regenerateToken();

        return redirect()
            ->route('customer.login')
            ->with('status', 'Вы вышли из клиентского кабинета.');
    }
}
