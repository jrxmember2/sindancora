<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('condominium_managers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('condominium_id');
            $table->uuid('person_id');
            $table->string('role', 20); // sindico, subsindico, conselheiro
            $table->date('start_date');
            $table->date('end_date')->nullable(); // null = mandato ativo
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('condominium_id')->references('id')->on('condominiums')->cascadeOnDelete();
            $table->foreign('person_id')->references('id')->on('persons')->cascadeOnDelete();
            $table->index(['tenant_id', 'condominium_id', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('condominium_managers');
    }
};
