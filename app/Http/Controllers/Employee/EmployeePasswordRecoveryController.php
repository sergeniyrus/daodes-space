<?php

namespace App\Http\Controllers\Employee;

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

class EmployeePasswordRecoveryController extends Controller
{
    public function create(): View
    {
        return view('employee.auth.password-reset');
    }

    public function sendCode(Request $request, AccountPasswordRecoveryService $recovery): RedirectResponse
    {
        $validator = Validator::make($request->all(), ['phone' => ['required', 'string', 'max:32']]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($request->only('phone'));
        }
        try {
            $phone = EmployeePhone::normalize($validator->validated()['phone']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['phone' => $exception->getMessage()])->withInput($request->only('phone'));
        }
        try {
            $recovery->request('employee', $phone);
        } catch (CustomerOtpRateLimitException|SmsTransportException $exception) {
            return back()->withErrors(['phone' => $exception->getMessage()])->withInput(['phone' => $phone]);
        }
        return back()->with('status', 'Если для этого номера есть активная учётная запись, код восстановления отправлен.')
            ->with('recovery_step', 'verify')
            ->with('recovery_phone', $phone)
            ->withInput(['phone' => $phone]);
    }

    public function reset(Request $request, AccountPasswordRecoveryService $recovery): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'max:32'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:12', 'max:255'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($request->except(['code', 'password', 'password_confirmation']))
                ->with('recovery_step', 'verify')
                ->with('recovery_phone', $request->input('phone'));
        }
        try {
            $phone = EmployeePhone::normalize($validator->validated()['phone']);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['phone' => $exception->getMessage()]);
        }
        if (!$recovery->reset('employee', $phone, $validator->validated()['code'], $validator->validated()['password'])) {
            return back()->withErrors(['code' => 'Код неверен, истёк или уже использован. Запросите новый код.'])
                ->withInput(['phone' => $phone])
                ->with('recovery_step', 'verify')
                ->with('recovery_phone', $phone);
        }
        return redirect()->route('employee.login')->with('status', 'Пароль изменён. Теперь войдите с новым паролем.');
    }
}