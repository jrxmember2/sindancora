<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('common_areas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('condominium_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('capacity')->nullable();
            $table->boolean('requires_approval')->default(true);
            $table->integer('min_advance_days')->default(0); // antecedência mínima
            $table->time('opening_time')->nullable();
            $table->time('closing_time')->nullable();
            $table->decimal('fee', 10, 2)->nullable();     // taxa de uso
            $table->decimal('deposit', 10, 2)->nullable();  // caução
            $table->text('rules')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('condominium_id')->references('id')->on('condominiums')->cascadeOnDelete();
            $table->index(['tenant_id', 'condominium_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('common_areas');
    }
};
