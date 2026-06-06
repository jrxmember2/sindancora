<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Veículos cadastrados por unidade (útil para portaria/controle de acesso).
        Schema::create('vehicles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('unit_id');
            $table->string('type', 20)->default('car'); // car|motorcycle|truck|bike|other
            $table->string('plate', 10)->nullable();
            $table->string('brand_model')->nullable();   // marca/modelo
            $table->string('color', 30)->nullable();
            $table->string('parking_spot', 30)->nullable(); // vaga
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->index(['tenant_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
