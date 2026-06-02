<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('condominium_id');
            $table->uuid('unit_id');
            $table->uuid('person_id')->nullable();      // responsável (snapshot na geração)
            $table->uuid('batch_id')->nullable();       // agrupa cobranças geradas em lote
            $table->string('type', 20)->default('condo_fee'); // condo_fee, extra, fine, other
            $table->string('description');
            $table->string('reference_month', 7)->nullable(); // YYYY-MM
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->decimal('fine_rate', 5, 2)->default(0);     // multa % sobre o valor
            $table->decimal('interest_rate', 5, 2)->default(0); // juros % ao mês (pró-rata/dia)
            $table->string('status', 20)->default('pending');   // pending, paid, overdue, cancelled
            $table->timestamp('paid_at')->nullable();
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->uuid('receipt_storage_object_id')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('condominium_id')->references('id')->on('condominiums')->cascadeOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->foreign('person_id')->references('id')->on('persons')->nullOnDelete();
            $table->foreign('receipt_storage_object_id')->references('id')->on('storage_objects')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'condominium_id']);
            $table->index(['unit_id']);
            $table->index(['due_date']);
            $table->index(['batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};
