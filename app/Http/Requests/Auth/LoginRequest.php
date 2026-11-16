<?php

namespace App\Http\Requests\Auth;

use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /** Failed attempts allowed per login name + IP before a lockout. */
    private const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            $this->loginField() => Str::lower(trim($this->string('login'))),
            'password' => $this->string('password')->value(),
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            // Security audit trail (the password is never logged).
            ActivityLogger::log('login_failed', 'auth', sprintf('Failed sign-in for "%s"', Str::limit($this->string('login')->trim()->value(), 100)));

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        // Correct password but the account is deactivated: refuse the login.
        if (! Auth::user()->is_active) {
            ActivityLogger::log('login_blocked', 'auth', 'Sign-in refused: account is deactivated', Auth::user(), user: Auth::user());
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'login' => 'Your account has been deactivated. Please contact an administrator.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));
        ActivityLogger::log('login_locked', 'auth', sprintf('Sign-in locked after too many attempts for "%s"', Str::limit($this->string('login')->trim()->value(), 100)));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')) . '|' . $this->ip());
    }

    /** "dara@shop.com" logs in by email, "dara" by username. */
    public function loginField(): string
    {
        return filter_var($this->string('login')->trim()->value(), FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
    }

    public function attributes(): array
    {
        return ['login' => 'username or email'];
    }
}
