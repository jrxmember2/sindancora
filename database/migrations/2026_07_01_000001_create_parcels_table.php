<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Encomendas/correspondências recebidas na portaria. O porteiro registra a chegada,
        // o morador da unidade é notificado e a retirada é dada como baixa.
        Schema::create('parcels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('condominium_id');
            $table->uuid('unit_id');
            $table->string('description');
            $table->string('carrier')->nullable();          // transportadora/remetente
            $table->string('tracking_code')->nullable();
            $table->string('status', 20)->default('awaiting'); // awaiting | picked_up
            $table->uuid('received_by')->nullable();         // quem registrou (porteiro/gestor)
            $table->timestamp('received_at')->useCurrent();
            $table->uuid('picked_up_by')->nullable();        // quem deu baixa
            $table->timestamp('picked_up_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('condominium_id')->references('id')->on('condominiums')->cascadeOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->foreign('received_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('picked_up_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'condominium_id', 'status']);
            $table->index(['unit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcels');
    }
};
