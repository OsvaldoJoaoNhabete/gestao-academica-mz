<?php

declare(strict_types=1);

namespace App\Auth;

use App\Enums\UserRole;
use App\Models\Scopes\TenantScope;
use App\Services\TenantManager;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Eloquent\Builder;

final class TenantUserProvider extends EloquentUserProvider
{
    protected function newModelQuery($model = null): Builder
    {
        // Authentication must not depend on the authenticated user, avoiding scope recursion.
        $model ??= $this->createModel();
        $query = $model->newQuery()->withoutGlobalScope(TenantScope::class);
        $manager = app(TenantManager::class);
        if ($manager->id() !== null) {
            $query->where('tenant_id', $manager->id())->where('role', '!=', UserRole::SuperAdmin->value);
        } elseif ($manager->isCentral()) {
            $query->whereNull('tenant_id')->where('role', UserRole::SuperAdmin->value);
        } else {
            $query->whereRaw('1 = 0');
        }
        if ($this->queryCallback !== null) {
            ($this->queryCallback)($query);
        }

        return $query;
    }
}
