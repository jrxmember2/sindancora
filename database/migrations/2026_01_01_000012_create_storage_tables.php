<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_objects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('condominium_id')->nullable();
            $table->string('entity_type', 100)->nullable(); // 'document', 'announcement', etc.
            $table->uuid('entity_id')->nullable();
            $table->string('storage_provider', 50)->default('local'); // r2, minio, s3, local
            $table->string('storage_bucket', 255)->nullable();
            $table->string('storage_path', 1000);
            $table->string('original_filename', 500)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->bigInteger('file_size_bytes');
            $table->string('checksum_sha256', 64)->nullable();
            $table->string('visibility', 30)->default('tenant'); // private, tenant, condominium, public_to_residents
            $table->uuid('uploaded_by')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('permanent_delete_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id'], 'idx_storage_tenant');
            $table->index(['tenant_id', 'entity_type', 'entity_id'], 'idx_storage_entity');
        });

        Schema::create('storage_usage_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->bigInteger('total_bytes');
            $table->integer('total_files');
            $table->timestamp('snapshot_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'snapshot_at']);
        });

        Schema::create('storage_quota_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->integer('size_gb');
            $table->decimal('price_monthly', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('tenant_storage_addons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('package_id')->nullable();
            $table->integer('size_gb');
            $table->decimal('price_paid', 10, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('starts_at')->useCurrent();
            $table->timestamp('ends_at')->nullable();
            $table->uuid('added_by')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('package_id')->references('id')->on('storage_quota_packages')->nullOnDelete();
            $table->index(['tenant_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_storage_addons');
        Schema::dropIfExists('storage_quota_packages');
        Schema::dropIfExists('storage_usage_snapshots');
        Schema::dropIfExists('storage_objects');
    }
};
