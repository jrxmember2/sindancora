<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('condominium_id');
            $table->uuid('block_id')->nullable();
            $table->string('number', 20);
            $table->smallInteger('floor')->nullable();
            $table->string('type', 30)->default('apartment'); // apartment, house, commercial, garage, storage
            $table->decimal('area_m2', 8, 2)->nullable();
            $table->decimal('fraction', 10, 6)->nullable();
            $table->string('status', 20)->default('vacant'); // occupied, vacant, under_renovation
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('condominium_id')->references('id')->on('condominiums')->cascadeOnDelete();
            $table->foreign('block_id')->references('id')->on('blocks')->nullOnDelete();
            $table->unique(['condominium_id', 'block_id', 'number']);
            $table->index(['tenant_id', 'condominium_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
