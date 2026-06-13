<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Configuração singleton do billing SaaS: prazos da régua de cobrança, regra de desbloqueio por
 * confiança e parâmetros fiscais da NFS-e (emitida via Asaas). Tudo configurável no super admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Régua de cobrança (offsets em dias relativos ao vencimento)
            $table->integer('reminder_days_before')->default(3); // D-3
            $table->integer('overdue_day_1')->default(3);        // D+3
            $table->integer('overdue_day_2')->default(7);        // D+7
            $table->integer('overdue_day_3')->default(12);       // D+12
            $table->integer('suspend_day')->default(15);         // D+15 → suspended

            // Desbloqueio por confiança (automático)
            $table->boolean('trust_unlock_enabled')->default(true);
            $table->integer('trust_min_months')->default(6);       // N meses como cliente
            $table->integer('trust_tolerance_days')->default(0);   // até X dias de atraso conta como "em dia"
            $table->integer('trust_cooldown_months')->default(12); // sem outra carência nos últimos Z meses
            $table->integer('trust_grace_days')->default(10);      // Y dias de carência extra

            // Config fiscal NFS-e (Asaas /invoices)
            $table->boolean('nfse_enabled')->default(false);
            $table->string('nfse_service_description', 500)->nullable();
            $table->string('nfse_municipal_service_code', 30)->nullable();
            $table->decimal('nfse_iss_tax', 6, 2)->nullable();        // alíquota ISS (%)
            $table->decimal('nfse_deductions', 12, 2)->nullable();
            $table->string('nfse_observations', 1000)->nullable();
            $table->boolean('nfse_send_email_to_customer')->default(true);

            $table->timestamps();
        });

        // Linha singleton (defaults do prompt).
        DB::table('billing_settings')->insert([
            'id' => (string) Str::uuid(),
            'reminder_days_before' => 3,
            'overdue_day_1' => 3,
            'overdue_day_2' => 7,
            'overdue_day_3' => 12,
            'suspend_day' => 15,
            'trust_unlock_enabled' => true,
            'trust_min_months' => 6,
            'trust_tolerance_days' => 0,
            'trust_cooldown_months' => 12,
            'trust_grace_days' => 10,
            'nfse_enabled' => false,
            'nfse_send_email_to_customer' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
