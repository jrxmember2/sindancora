<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable(); // null = role global do sistema
            $table->string('name', 50);
            $table->string('display_name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false); // roles imutáveis do sistema
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id']);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('module', 50);
            $table->string('action', 50);
            $table->string('name', 100)->unique(); // module:action
            $table->text('description')->nullable();

            $table->index(['module', 'action']);
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('role_id');
            $table->uuid('permission_id');

            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->unique(['role_id', 'permission_id']);
            $table->index('role_id');
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('role_id');
            $table->uuid('condominium_id')->nullable(); // escopo opcional por condomínio
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->unique(['user_id', 'role_id', 'condominium_id']);
            $table->index(['user_id']);
            $table->index(['role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
