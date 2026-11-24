<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\TenantUserProvider;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantManager::class);
    }

    public function boot(): void
    {
        Auth::provider('tenant-eloquent', fn ($app, array $config) => new TenantUserProvider($app['hash'], $config['model']));
        foreach (UserRole::cases() as $role) {
            Gate::define('dashboard.'.$role->value, function (User $user) use ($role): bool {
                $manager = app(TenantManager::class);

                return $user->is_active && $user->role === $role && ($role === UserRole::SuperAdmin
                    ? $manager->isCentral() && $user->tenant_id === null
                    : $manager->id() !== null && $user->tenant_id === $manager->id());
            });
        }
    }
}
