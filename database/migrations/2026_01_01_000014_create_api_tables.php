<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name', 100);
            $table->string('key_hash', 64)->unique();
            $table->string('key_prefix', 12);
            $table->jsonb('scopes')->default('[]');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id']);
        });

        Schema::create('api_key_scopes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('scope', 100)->unique();
            $table->text('description')->nullable();
            $table->string('module', 50)->nullable();
        });

        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->uuid('api_key_id')->nullable();
            $table->string('method', 10)->nullable();
            $table->string('path', 500)->nullable();
            $table->smallInteger('status_code')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->uuid('request_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at'], 'idx_api_logs_tenant');
            $table->index(['api_key_id', 'created_at'], 'idx_api_logs_key');
        });

        Schema::create('webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('url', 500);
            $table->string('description', 200)->nullable();
            $table->jsonb('events')->default('[]');
            $table->string('secret', 64)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'active']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('webhook_id');
            $table->string('event', 100);
            $table->jsonb('payload')->nullable();
            $table->smallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->smallInteger('attempts')->default(1);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('webhook_id')->references('id')->on('webhooks')->cascadeOnDelete();
            $table->index(['webhook_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
        Schema::dropIfExists('api_request_logs');
        Schema::dropIfExists('api_key_scopes');
        Schema::dropIfExists('api_keys');
    }
};
