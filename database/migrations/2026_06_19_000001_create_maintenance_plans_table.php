<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Planos de manutenção preventiva recorrente (elevador, bombas, AVCB, etc.).
        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('condominium_id');                 // onde acontece (obrigatório)
            $table->uuid('supplier_id')->nullable();        // fornecedor padrão
            $table->string('category', 60)->nullable();     // slug (categorias customizáveis tipo 'maintenance')
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('frequency', 20);                // once|monthly|quarterly|semiannual|annual|biennial
            $table->date('next_due_date');                  // próxima execução prevista
            $table->integer('alert_days')->default(15);     // antecedência do alerta
            $table->date('last_done_date')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('condominium_id')->references('id')->on('condominiums')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->index(['tenant_id', 'condominium_id', 'is_active']);
            $table->index(['tenant_id', 'next_due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_plans');
    }
};
