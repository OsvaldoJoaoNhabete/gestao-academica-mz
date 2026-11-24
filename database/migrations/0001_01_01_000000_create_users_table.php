<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
            $table->string('email');
            $table->string('user_code')->nullable();
            $table->string('phone')->nullable();
            $table->enum('role', ['super_admin', 'direcao', 'registo_academico', 'tesouraria', 'docente', 'estudante', 'encarregado'])->default('estudante');
            $table->boolean('is_active')->default(true);
            $table->unique(['tenant_id', 'email']);
            $table->unique(['tenant_id', 'user_code']);
            $table->unique(['tenant_id', 'phone']);
            // NULL does not collide in unique indexes; scope 0 protects central identities.
            $table->bigInteger('identity_scope')->storedAs('COALESCE(tenant_id, 0)');
            $table->unique(['identity_scope', 'email']);
            $table->unique(['identity_scope', 'user_code']);
            $table->unique(['identity_scope', 'phone']);
            $table->index(['tenant_id', 'role']);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
