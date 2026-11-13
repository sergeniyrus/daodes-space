<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\CustomerOtpRateLimitException;
use App\Exceptions\SmsTransportException;
use App\Http\Controllers\Controller;
use App\Services\AccountPasswordRecoveryService;
use App\Support\EmployeePhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use InvalidArgumentException;

class CustomerPasswordRecoveryController extends Controller
{
    public function create(): View
    {
        return view('customer.auth.password-reset');
    }

    public function sendCode(Request $request, AccountPasswordRecoveryService $recovery): RedirectResponse
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
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['phone' => $exception->getMessage()])->withInput($request->only(['phone', 'name']));
        }
        try {
            $recovery->request('customer', $phone, $validator->validated()['name'] ?? null);
        } catch (CustomerOtpRateLimitException|SmsTransportException $exception) {
            return back()->withErrors(['phone' => $exception->getMessage()])
                ->withInput(['phone' => $phone, 'name' => $validator->validated()['name'] ?? null]);
        }
        return back()->with('status', 'Если SMS-провайдер доступен, код подтверждения отправлен. Он действует 10 минут.')
            ->withInput(['phone' => $phone, 'name' => $validator->validated()['name'] ?? null]);
    }

    public function reset(Request $request, AccountPasswordRecoveryService $recovery): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'max:32'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:12', 'max:255', 'confirmed'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($request->except(['code', 'password', 'password_confirmation']));
        }
        try {
            $phone = EmployeePhone::normalize($validator->validated()['phone']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['phone' => $exception->getMessage()]);
        }
        if (!$recovery->reset('customer', $phone, $validator->validated()['code'], $validator->validated()['password'])) {
            return back()->withErrors(['code' => 'Код неверен, истёк или уже использован. Запросите новый код.'])
                ->withInput(['phone' => $phone]);
        }
        return redirect()->route('customer.login')->with('status', 'Пароль сохранён. Теперь можно входить по телефону и паролю без SMS.');
    }
}