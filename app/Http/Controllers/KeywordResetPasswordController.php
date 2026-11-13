<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Services\EncryptionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class KeywordResetPasswordController extends Controller
{
    protected $encryptionService;

    public function __construct(EncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
    }

    public function showKeywordForm()
    {
        return view('auth.forgot-password');
    }

    public function submitKeyword(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'keyword' => ['required', 'string', 'max:255'],
        ]);

        $username = (string) $request->input('username');
        $key = 'partner-password-reset:' . hash('sha256', Str::lower($username) . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'general' => "Слишком много попыток. Повторите через {$seconds} сек.",
            ]);
        }

        $user = User::where('name', $username)->first();
        if (!$user) {
            RateLimiter::hit($key, 900);
            return back()->withErrors(['general' => __('message.incorrect_credentials')]);
        }

        try {
            $decryptedKeyword = $this->encryptionService->decrypt($user->keyword);
            if (!hash_equals($decryptedKeyword, (string) $request->input('keyword'))) {
                RateLimiter::hit($key, 900);
                return back()->withErrors(['general' => __('message.incorrect_credentials')]);
            }

            RateLimiter::clear($key);
            $token = Str::random(60);
            Session::put('password_reset_token', $token);
            Session::put('password_reset_user_id', $user->id);
            Session::put('password_reset_expires_at', now()->addMinutes(15)->timestamp);
            $user->forceFill(['remember_token' => Hash::make($token)])->save();
            $request->session()->regenerate();

            Log::info('Partner password reset keyword verified', ['user_id' => $user->id]);

            return redirect()->route('partner.password.reset')
                ->with('status', __('message.keyword_verified'));
        } catch (\Throwable $exception) {
            Log::error('Partner password reset keyword verification failed', [
                'user_id' => $user->id,
                'exception' => class_basename($exception),
            ]);
            return back()->withErrors(['general' => __('message.decryption_error')]);
        }
    }

    public function showResetForm()
    {
        $expiresAt = (int) Session::get('password_reset_expires_at', 0);
        if (!Session::has('password_reset_token') || !Session::has('password_reset_user_id') || $expiresAt < now()->timestamp) {
            Session::forget(['password_reset_token', 'password_reset_user_id', 'password_reset_expires_at']);
            return redirect()->route('password.request')
                ->withErrors(['general' => __('message.invalid_token')]);
        }
        return view('auth.reset-password');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string', 'min:12', 'max:255', 'confirmed'],
        ]);

        $token = Session::get('password_reset_token');
        $userId = Session::get('password_reset_user_id');
        $expiresAt = (int) Session::get('password_reset_expires_at', 0);

        if (!$token || !$userId || $expiresAt < now()->timestamp) {
            Session::forget(['password_reset_token', 'password_reset_user_id', 'password_reset_expires_at']);
            return back()->withErrors(['general' => __('message.invalid_token')]);
        }

        $user = User::find($userId);
        if (!$user || !Hash::check($token, (string) $user->remember_token)) {
            Log::warning('Invalid partner password reset token attempted');
            Session::forget(['password_reset_token', 'password_reset_user_id', 'password_reset_expires_at']);
            return back()->withErrors(['general' => __('message.invalid_token')]);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => null,
        ])->save();

        Session::forget(['password_reset_token', 'password_reset_user_id', 'password_reset_expires_at']);
        Log::info('Partner password reset successful', ['user_id' => $user->id]);

        return redirect()->route('partner.login')
            ->with('status', __('message.password_reset_success'));
    }
}