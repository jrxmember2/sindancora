<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('action', 100); // created, updated, deleted, login, etc.
            $table->string('entity', 100)->nullable(); // App\Models\User
            $table->uuid('entity_id')->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('url', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at'], 'idx_audit_tenant_created');
            $table->index(['tenant_id', 'entity', 'entity_id'], 'idx_audit_entity');
            $table->index(['user_id', 'created_at'], 'idx_audit_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
