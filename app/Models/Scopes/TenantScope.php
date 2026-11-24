<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Enums\UserRole;
use App\Services\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $manager = app(TenantManager::class);
        if ($manager->id() !== null) {
            $builder->where($model->qualifyColumn('tenant_id'), $manager->id());
        } elseif (! ($manager->isCentral() && Auth::user()?->role === UserRole::SuperAdmin && Auth::user()->is_active)) {
            $builder->whereRaw('1 = 0');
        }
    }
}
