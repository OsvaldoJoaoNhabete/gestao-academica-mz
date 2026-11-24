<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tenancy.central_domain' => 'sigacloud.test']);
        $this->withoutVite();
        $this->seed();
    }

    private function manager(): TenantManager
    {
        return app(TenantManager::class);
    }

    private function demo(): Tenant
    {
        return Tenant::where('slug', 'demo')->firstOrFail();
    }

    private function user(UserRole $role = UserRole::Estudante): User
    {
        return $this->manager()->run($this->demo(), fn () => User::where('role', $role)->firstOrFail());
    }

    private function other(): Tenant
    {
        return Tenant::create(['name' => 'Outra escola', 'slug' => 'outra', 'subdomain' => 'outra', 'domain' => 'portal.outra.ac.mz', 'email' => 'info@outra.ac.mz', 'phone' => '123', 'status' => 'active', 'settings' => []]);
    }

    public function test_host_resolution_branding_and_unknown_hosts(): void
    {
        $this->get('http://demo.sigacloud.test/login')->assertOk()->assertSee('Instituto Superior Politécnico Exemplo')->assertSee('#122D50');
        $other = $this->other();
        $this->get('http://portal.outra.ac.mz/login')->assertOk()->assertSee($other->name);
        $this->get('http://desconhecido.sigacloud.test/login')->assertNotFound();
        $this->get('http://demo.attacker.test/login')->assertNotFound();
        $this->get('http://sigacloud.test/login')->assertOk()->assertSee('Administração central');
        $this->assertNull($this->manager()->current());
        $this->assertFalse($this->manager()->isCentral());
    }

    public function test_suspended_tenant_denies_login_and_dashboard(): void
    {
        $this->demo()->update(['status' => 'suspended']);
        $this->get('http://demo.sigacloud.test/login')->assertForbidden()->assertSee('Instituição suspensa');
        $this->post('http://demo.sigacloud.test/login', ['identity' => 'DOC001', 'password' => 'password'])->assertForbidden();
    }

    public function test_all_tenant_profiles_login_and_receive_their_dashboard(): void
    {
        foreach (UserRole::cases() as $role) {
            if ($role === UserRole::SuperAdmin) {
                continue;
            }
            Auth::forgetGuards();
            $this->flushSession();
            $this->post('http://demo.sigacloud.test/login', ['identity' => $role->value.'@demo.sigacloud.co.mz', 'password' => 'password'])
                ->assertRedirect('http://demo.sigacloud.test/'.$role->value.'/dashboard');
            $this->get('http://demo.sigacloud.test/'.$role->value.'/dashboard')->assertOk()->assertSee($role->label());
            $this->post('http://demo.sigacloud.test/logout')->assertRedirect('http://demo.sigacloud.test/login');
            $this->assertGuest();
        }
    }

    public function test_code_and_phone_login(): void
    {
        foreach (['DOC001', '20260001', '+258840000004'] as $identity) {
            Auth::forgetGuards();
            $this->flushSession();
            $this->post('http://demo.sigacloud.test/login', ['identity' => $identity, 'password' => 'password'])->assertRedirect();
            $this->assertAuthenticated();
            $this->post('http://demo.sigacloud.test/logout');
        }
    }

    public function test_central_login_and_role_authorization(): void
    {
        $this->post('http://sigacloud.test/login', ['identity' => 'admin@sigacloud.co.mz', 'password' => 'password'])->assertRedirect('http://sigacloud.test/super-admin/dashboard');
        $this->get('http://sigacloud.test/super-admin/dashboard')->assertOk();
        $this->get('http://sigacloud.test/docente/dashboard')->assertForbidden();
        $this->post('http://sigacloud.test/logout');
        Auth::forgetGuards();
        $this->post('http://demo.sigacloud.test/login', ['identity' => 'admin@sigacloud.co.mz', 'password' => 'password'])->assertSessionHasErrors('identity');
    }

    public function test_user_cannot_reuse_session_across_tenants_or_central_domain(): void
    {
        $this->other();
        $this->actingAs($this->user());
        $this->get('http://outra.sigacloud.test/estudante/dashboard')->assertForbidden();
        $this->get('http://sigacloud.test/estudante/dashboard')->assertForbidden();
        $this->get('http://demo.sigacloud.test/tesouraria/dashboard')->assertForbidden();
    }

    public function test_inactive_users_and_wrong_passwords_are_rejected(): void
    {
        $user = $this->user();
        $this->manager()->run($this->demo(), fn () => $user->forceFill(['is_active' => false])->save());
        $this->post('http://demo.sigacloud.test/login', ['identity' => $user->email, 'password' => 'password'])->assertSessionHasErrors('identity');
        $this->assertGuest();
        $this->actingAs($user)->get('http://demo.sigacloud.test/estudante/dashboard')->assertForbidden();
    }

    public function test_login_rate_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('http://demo.sigacloud.test/login', ['identity' => 'DOC001', 'password' => 'wrong'])->assertSessionHasErrors('identity');
        }
        $this->post('http://demo.sigacloud.test/login', ['identity' => 'DOC001', 'password' => 'password'])->assertSessionHasErrors('identity');
        $this->assertGuest();
    }

    public function test_queries_fail_closed_and_creation_sets_tenant(): void
    {
        $this->assertSame(0, User::count());
        $this->manager()->run($this->demo(), function (): void {
            $this->assertSame(6, User::count());
            $user = User::factory()->create(['role' => UserRole::Estudante]);
            $this->assertSame($this->demo()->id, $user->tenant_id);
        });
        $this->manager()->run($this->other(), fn () => $this->assertSame(0, User::count()));
    }

    public function test_forged_tenant_creation_is_rejected(): void
    {
        $other = $this->other();
        $this->expectException(LogicException::class);
        $this->manager()->run($this->demo(), fn () => User::factory()->create(['tenant_id' => $other->id, 'role' => UserRole::Estudante]));
    }

    public function test_tenant_transfer_is_rejected(): void
    {
        $user = $this->user();
        $other = $this->other();
        $this->expectException(LogicException::class);
        $this->manager()->run($this->demo(), fn () => $user->forceFill(['tenant_id' => $other->id])->save());
    }

    public function test_same_email_and_code_allowed_between_tenants(): void
    {
        $user = $this->user();
        $new = $this->manager()->run($this->other(), fn () => User::factory()->create(['email' => $user->email, 'user_code' => $user->user_code, 'role' => UserRole::Estudante]));
        $this->assertNotSame($user->tenant_id, $new->tenant_id);
    }

    public function test_duplicate_email_in_same_tenant_is_rejected(): void
    {
        $this->expectException(QueryException::class);
        $this->manager()->run($this->demo(), fn () => User::factory()->create(['email' => $this->user()->email, 'role' => UserRole::Estudante]));
    }

    public function test_ambiguous_cross_field_identity_cannot_login(): void
    {
        $this->manager()->run($this->demo(), fn () => User::factory()->create(['email' => 'other@demo.test', 'user_code' => 'docente@demo.sigacloud.co.mz', 'role' => UserRole::Estudante]));
        $this->post('http://demo.sigacloud.test/login', ['identity' => 'docente@demo.sigacloud.co.mz', 'password' => 'password'])->assertSessionHasErrors('identity');
        $this->assertGuest();
    }

    public function test_files_are_private_and_logo_is_scoped_to_host(): void
    {
        Storage::fake('local');
        $tenant = $this->demo();
        $path = $this->manager()->run($tenant, fn () => $this->manager()->store(UploadedFile::fake()->image('logo.png'), 'branding'));
        $this->assertStringStartsWith('tenants/'.$tenant->id.'/branding/', $path);
        $tenant->update(['settings' => array_merge($tenant->settings, ['logo_path' => $path])]);
        $this->get('http://demo.sigacloud.test/branding/logo')->assertOk();
        $other = $this->other();
        $other->update(['settings' => ['logo_path' => $path]]);
        $this->get('http://portal.outra.ac.mz/branding/logo')->assertNotFound();
    }

    public function test_seed_is_repeatable(): void
    {
        $this->seed();
        $this->assertSame(7, DB::table('users')->count());
        $this->assertSame(1, Tenant::count());
    }

    public function test_authentication_provider_resolves_session_identity_within_host(): void
    {
        $user = $this->user();
        $other = $this->other();
        $provider = Auth::createUserProvider('users');
        $this->manager()->run($this->demo(), fn () => $this->assertSame($user->id, $provider->retrieveById($user->id)?->id));
        $this->manager()->run($other, fn () => $this->assertNull($provider->retrieveById($user->id)));
        $this->manager()->set(null, true);
        try {
            $this->assertNull($provider->retrieveById($user->id));
        } finally {
            $this->manager()->clear();
        }
    }

    public function test_super_admin_can_read_across_tenants_only_on_central_host(): void
    {
        $this->manager()->set(null, true);
        $admin = User::withoutGlobalScopes()->whereNull('tenant_id')->firstOrFail();
        $this->actingAs($admin);
        $this->assertSame(7, User::count());
        $this->manager()->run($this->demo(), fn () => $this->assertSame(6, User::count()));
        $this->manager()->clear();
        $this->assertSame(0, User::count());
    }

    public function test_duplicate_central_email_is_rejected_even_with_null_tenant(): void
    {
        $this->manager()->set(null, true);
        $this->expectException(QueryException::class);
        try {
            User::factory()->create(['role' => UserRole::SuperAdmin, 'email' => 'admin@sigacloud.co.mz']);
        } finally {
            $this->manager()->clear();
        }
    }

    public function test_logo_path_traversal_is_rejected(): void
    {
        $this->demo()->update(['settings' => ['logo_path' => 'tenants/'.$this->demo()->id.'/../../.env']]);
        $this->get('http://demo.sigacloud.test/branding/logo')->assertNotFound();
    }

    public function test_tenant_context_is_restored_after_exception(): void
    {
        try {
            $this->manager()->run($this->demo(), fn () => throw new LogicException('test'));
        } catch (LogicException) {
            $this->assertNull($this->manager()->current());
        }
    }

    public function test_malformed_login_identity_is_a_validation_error(): void
    {
        $this->post('http://demo.sigacloud.test/login', ['identity' => ['DOC001'], 'password' => 'password'])->assertSessionHasErrors('identity');
    }
}
