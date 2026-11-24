<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\BelongsToTenant;
use App\Services\TenantManager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LogicException;

class User extends Authenticatable
{
    use BelongsToTenant, HasFactory, Notifiable;

    protected $attributes = ['role' => 'estudante', 'is_active' => true];

    protected $fillable = ['name', 'email', 'user_code', 'phone', 'password'];

    protected $hidden = ['password', 'remember_token', 'identity_scope'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'role' => UserRole::class, 'is_active' => 'boolean', 'tenant_id' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if (! $user->exists && $user->tenant_id === null && app(TenantManager::class)->id() !== null) {
                $user->tenant_id = app(TenantManager::class)->id();
            }
            if (($user->role === UserRole::SuperAdmin) !== ($user->tenant_id === null)) {
                throw new LogicException('Só o super administrador pode pertencer ao domínio central.');
            }
            $user->email = mb_strtolower(trim($user->email));
        });
    }
}
