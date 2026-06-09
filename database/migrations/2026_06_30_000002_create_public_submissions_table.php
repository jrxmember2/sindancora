<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('public_submissions')) {
            Schema::create('public_submissions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('condominium_id');
                // resident_signup | occurrence
                $table->string('type', 30);
                // pending | approved | rejected
                $table->string('status', 20)->default('pending');

                // Campos de contato denormalizados para listagem/busca rápida na fila.
                $table->string('name', 150)->nullable();
                $table->string('email', 150)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('document', 30)->nullable();

                // Dados completos do envio (relação, unidade pretendida, título/descrição etc.).
                $table->json('payload')->nullable();

                $table->uuid('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_notes')->nullable();

                // Referências geradas na aprovação.
                $table->uuid('person_id')->nullable();
                $table->uuid('occurrence_id')->nullable();

                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('condominium_id')->references('id')->on('condominiums')->cascadeOnDelete();
                $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('person_id')->references('id')->on('persons')->nullOnDelete();
                $table->foreign('occurrence_id')->references('id')->on('occurrences')->nullOnDelete();

                $table->index(['tenant_id', 'status', 'type']);
                $table->index(['tenant_id', 'condominium_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('public_submissions');
    }
};
