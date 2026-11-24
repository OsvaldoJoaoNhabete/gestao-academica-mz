<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->ensureNotThrottled();
        $identity = $request->string('identity')->toString();
        $manager = app(TenantManager::class);
        $query = User::withoutGlobalScope(TenantScope::class);
        if ($manager->id() !== null) {
            $query->where('tenant_id', $manager->id())->where('role', '!=', UserRole::SuperAdmin->value);
        } elseif ($manager->isCentral()) {
            $query->whereNull('tenant_id')->where('role', UserRole::SuperAdmin->value);
        } else {
            abort(404);
        }
        $users = $query->where(function ($query) use ($identity): void {
            $query->where('email', mb_strtolower($identity))->orWhere('user_code', $identity)->orWhere('phone', $identity);
        })->limit(2)->get();
        $user = $users->count() === 1 ? $users->first() : null;
        // Always hash-check, including unknown or ambiguous identities.
        $valid = Hash::check($request->string('password')->toString(), $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (! $user || ! $valid || ! $user->is_active) {
            RateLimiter::hit($request->throttleKey(), 60);
            throw ValidationException::withMessages(['identity' => 'Os dados de acesso são inválidos.']);
        }
        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => Hash::make($request->string('password')->toString())])->save();
        }
        Auth::login($user);
        $request->session()->regenerate();
        RateLimiter::clear($request->throttleKey());

        return redirect()->route($user->role->dashboard());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
