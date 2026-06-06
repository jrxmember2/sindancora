<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quais condomínios cada fornecedor atende (opcional; fornecedor é do tenant).
        Schema::create('supplier_condominium', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_id');
            $table->uuid('condominium_id');

            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
            $table->foreign('condominium_id')->references('id')->on('condominiums')->cascadeOnDelete();
            $table->unique(['supplier_id', 'condominium_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_condominium');
    }
};
