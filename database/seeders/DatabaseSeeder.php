<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('As contas de demonstração não podem ser criadas em produção.');
        }
        $manager = app(TenantManager::class);
        try {
            DB::transaction(function () use ($manager): void {
                $manager->set(null, true);
                $admin = User::withoutGlobalScope(TenantScope::class)->whereNull('tenant_id')->firstOrNew(['email' => 'admin@sigacloud.co.mz']);
                $admin->forceFill(['name' => 'Administrador SIGA-CLOUD', 'role' => UserRole::SuperAdmin, 'password' => 'password', 'is_active' => true])->save();
                $tenant = Tenant::updateOrCreate(['slug' => 'demo'], [
                    'name' => 'Instituto Superior Politécnico Exemplo', 'subdomain' => 'demo', 'domain' => null,
                    'email' => 'info@exemplo.ac.mz', 'phone' => '+258840000000', 'status' => 'trial',
                    'settings' => ['logo_path' => null, 'primary_color' => '#122D50', 'secondary_color' => '#D4AF37', 'regime' => 'semestral', 'grade_scale' => 20, 'exemption_grade' => 14, 'pass_grade' => 10],
                ]);
                $manager->run($tenant, function (): void {
                    $index = 0;
                    foreach (UserRole::cases() as $role) {
                        if ($role === UserRole::SuperAdmin) {
                            continue;
                        }
                        $index++;
                        $user = User::firstOrNew(['email' => $role->value.'@demo.sigacloud.co.mz']);
                        $user->forceFill(['name' => $role->label().' Demonstração', 'role' => $role, 'password' => 'password', 'is_active' => true,
                            'phone' => '+25884000000'.$index,
                            'user_code' => match ($role) {
                                UserRole::Docente => 'DOC001', UserRole::Estudante => '20260001', default => strtoupper($role->value).'001'
                            },
                        ])->save();
                    }
                });
            });
        } finally {
            $manager->clear();
        }
    }
}
