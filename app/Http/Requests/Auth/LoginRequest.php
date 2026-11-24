<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['identity' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'max:1024']];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('identity'))) {
            $this->merge(['identity' => trim($this->input('identity'))]);
        }
    }

    public function throttleKey(): string
    {
        return hash('sha256', $this->getHost().'|'.mb_strtolower((string) $this->input('identity')).'|'.$this->ip());
    }

    public function ensureNotThrottled(): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            throw ValidationException::withMessages(['identity' => 'Demasiadas tentativas. Aguarde '.RateLimiter::availableIn($this->throttleKey()).' segundos.']);
        }
    }
}
