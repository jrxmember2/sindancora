<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing SaaS (plataforma → tenant) via conta Asaas única do Sindâncora.
 * Distinto de tenant_payment_settings/charges (tenant → morador).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pré-cadastro do comprador. Nenhum tenant é criado antes da compensação do pagamento.
        Schema::create('pending_signups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('plan_id');
            $table->string('billing_cycle', 12)->default('monthly'); // monthly | yearly
            $table->string('billing_type', 20)->default('PIX');      // PIX | CREDIT_CARD | BOLETO
            $table->decimal('value', 12, 2);

            // Dados do comprador/síndico
            $table->string('company_name', 150);
            $table->string('document', 20)->nullable();   // CPF/CNPJ (dígitos)
            $table->string('email', 150);
            $table->string('phone', 30)->nullable();
            $table->string('admin_name', 150);

            // Referências no Asaas
            $table->string('asaas_customer_id')->nullable();
            $table->string('asaas_subscription_id')->nullable();
            $table->string('first_payment_id')->nullable();

            $table->string('status', 20)->default('pending'); // pending | paid | provisioned | failed | expired
            $table->uuid('tenant_id')->nullable();            // preenchido após provisionamento
            $table->text('error')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('plans');
            $table->index('status');
            $table->index('asaas_subscription_id');
            $table->index('asaas_customer_id');
        });

        // Assinatura SaaS do tenant (espelho da subscription do Asaas + máquina de estados de cobrança).
        Schema::create('billing_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('plan_id');
            $table->string('asaas_customer_id')->nullable();
            $table->string('asaas_subscription_id')->nullable();
            $table->string('billing_cycle', 12)->default('monthly');
            $table->string('billing_type', 20)->default('PIX');
            $table->decimal('value', 12, 2)->default(0);

            // active | overdue | suspended | canceled | grace_manual | grace_trust
            $table->string('status', 20)->default('active');
            $table->date('next_due_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('canceled_at')->nullable();

            // Carência (manual e por confiança)
            $table->date('grace_until')->nullable();
            $table->string('grace_reason', 500)->nullable();
            $table->uuid('grace_granted_by')->nullable();
            $table->timestamp('grace_granted_at')->nullable();
            $table->unsignedTinyInteger('trust_grace_count')->default(0);
            $table->timestamp('last_trust_grace_at')->nullable();

            // Estágios da régua já disparados para o ciclo vigente (evita reenvio)
            $table->json('dunning_state')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('plan_id')->references('id')->on('plans');
            $table->unique('tenant_id');
            $table->unique('asaas_subscription_id');
            $table->index('status');
            $table->index('next_due_date');
        });

        // Espelho dos payments do Asaas (+ vínculo com a NFS-e).
        Schema::create('billing_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->uuid('billing_subscription_id')->nullable();
            $table->string('asaas_payment_id')->unique();
            $table->string('asaas_subscription_id')->nullable();
            $table->string('asaas_customer_id')->nullable();

            $table->string('status', 30)->default('PENDING'); // status bruto do Asaas
            $table->string('billing_type', 20)->nullable();
            $table->decimal('value', 12, 2)->default(0);
            $table->decimal('net_value', 12, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('invoice_url')->nullable();
            $table->string('bank_slip_url')->nullable();
            $table->boolean('is_first_payment')->default(false);

            // NFS-e (Asaas /invoices)
            $table->string('invoice_id')->nullable();
            $table->string('nfse_status', 30)->nullable(); // scheduled | authorized | error | canceled
            $table->string('nfse_pdf_url')->nullable();
            $table->string('nfse_xml_url')->nullable();
            $table->text('nfse_error')->nullable();

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->foreign('billing_subscription_id')->references('id')->on('billing_subscriptions')->nullOnDelete();
            $table->index('status');
            $table->index('asaas_subscription_id');
        });

        // Auditoria de eventos recebidos do webhook (idempotência por event id).
        Schema::create('payment_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('asaas_event_id')->unique();
            $table->string('event', 60);
            $table->string('asaas_payment_id')->nullable();
            $table->json('payload');
            $table->boolean('processed')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('event');
            $table->index('asaas_payment_id');
        });

        // Linha do tempo por tenant: eventos financeiros, bloqueios, desbloqueios, e-mails enviados.
        Schema::create('billing_timeline', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('type', 40);          // payment, blocked, unblocked, grace, email, provisioned, dunning...
            $table->string('description', 500);
            $table->json('meta')->nullable();
            $table->uuid('actor_id')->nullable(); // usuário (super admin) quando ação manual
            $table->string('actor_name')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_timeline');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('billing_payments');
        Schema::dropIfExists('billing_subscriptions');
        Schema::dropIfExists('pending_signups');
    }
};
