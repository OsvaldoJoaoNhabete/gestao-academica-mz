<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

final class IdentifyTenant
{
    public function __construct(private readonly TenantManager $manager) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->manager->clear();
        try {
            $host = strtolower($request->getHost());
            $central = strtolower(config('tenancy.central_domain'));
            if ($host === $central) {
                $this->manager->set(null, true);
            } else {
                $tenant = Tenant::where('domain', $host)->first();
                if (! $tenant && str_ends_with($host, '.'.$central)) {
                    $subdomain = substr($host, 0, -strlen('.'.$central));
                    if (! str_contains($subdomain, '.')) {
                        $tenant = Tenant::where('subdomain', $subdomain)->first();
                    }
                }
                abort_unless($tenant, 404);
                if ($tenant->status === 'suspended') {
                    return response()->view('errors.suspended', ['institutionName' => $tenant->name], 403);
                }
                $this->manager->set($tenant);
            }
            View::share('currentTenant', $this->manager->current());

            return $next($request);
        } finally {
            $this->manager->clear();
            View::share('currentTenant', null);
        }
    }
}
