<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_unit_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('person_id');
            $table->uuid('unit_id');
            // owner = proprietário, tenant = locatário, resident = morador, dependent = dependente
            $table->string('type', 20)->default('resident');
            $table->boolean('is_primary')->default(false);
            $table->date('start_date');
            $table->date('end_date')->nullable(); // null = vínculo ativo
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('person_id')->references('id')->on('persons')->cascadeOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->index(['tenant_id', 'unit_id', 'end_date']);
            $table->index(['tenant_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_unit_links');
    }
};
