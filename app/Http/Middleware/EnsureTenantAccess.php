<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $manager = app(TenantManager::class);
        abort_unless($user && $user->is_active, 403);
        abort_unless($manager->id() !== null
            ? $user->tenant_id === $manager->id() && $user->role !== UserRole::SuperAdmin
            : $manager->isCentral() && $user->tenant_id === null && $user->role === UserRole::SuperAdmin, 403);

        return $next($request);
    }
}
