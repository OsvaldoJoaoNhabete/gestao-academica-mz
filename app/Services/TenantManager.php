<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\UploadedFile;
use LogicException;

final class TenantManager
{
    private ?Tenant $tenant = null;

    private bool $central = false;

    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function isCentral(): bool
    {
        return $this->central;
    }

    public function set(?Tenant $tenant, bool $central = false): void
    {
        $this->tenant = $tenant;
        $this->central = $tenant === null && $central;
    }

    public function clear(): void
    {
        $this->set(null);
    }

    public function run(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;
        $central = $this->central;
        $this->set($tenant);
        try {
            return $callback();
        } finally {
            $this->set($previous, $central);
        }
    }

    public function path(string $directory = ''): string
    {
        if (! $this->id()) {
            throw new LogicException('É necessário um contexto institucional.');
        }
        if (str_contains($directory, '..') || str_contains($directory, '\\') || str_starts_with($directory, '/')) {
            throw new LogicException('Directório inválido.');
        }

        return 'tenants/'.$this->id().($directory !== '' ? '/'.$directory : '');
    }

    public function store(UploadedFile $file, string $directory = 'uploads'): string
    {
        $path = $file->store($this->path($directory), 'local');
        if ($path === false) {
            throw new LogicException('Não foi possível guardar o ficheiro.');
        }

        return $path;
    }
}
