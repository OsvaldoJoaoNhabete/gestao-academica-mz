<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\UserRole;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);
        static::creating(function (Model $model): void {
            $manager = app(TenantManager::class);
            if ($manager->id() !== null) {
                if ($model->tenant_id !== null && (int) $model->tenant_id !== $manager->id()) {
                    throw new LogicException('A instituição não corresponde ao contexto activo.');
                }
                $model->tenant_id = $manager->id();
            } elseif (! ($model instanceof User && $model->role === UserRole::SuperAdmin && $model->tenant_id === null && $manager->isCentral())) {
                throw new LogicException('É necessário um contexto institucional para criar registos.');
            }
        });
        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                throw new LogicException('Não é permitido transferir registos entre instituições.');
            }
            static::assertTenantWrite($model);
        });
        static::deleting(fn (Model $model) => static::assertTenantWrite($model));
    }

    private static function assertTenantWrite(Model $model): void
    {
        $manager = app(TenantManager::class);
        if ($manager->id() !== null && (int) $model->tenant_id === $manager->id()) {
            return;
        }
        if ($model instanceof User && $model->tenant_id === null && $manager->isCentral() && $model->role === UserRole::SuperAdmin) {
            return;
        }
        throw new LogicException('Escrita fora do contexto institucional.');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
