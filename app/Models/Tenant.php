<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Tenant extends Model
{
    protected $fillable = ['name', 'slug', 'domain', 'subdomain', 'nuit', 'email', 'phone', 'address', 'status', 'settings'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function color(string $key): string
    {
        $color = $this->settings[$key] ?? '';

        return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/D', $color) ? $color : config('tenancy.'.$key);
    }
}
