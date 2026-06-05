<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Campanhas de disparo em massa por WhatsApp (Fase 6).
        Schema::create('wa_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('connection_id');
            $table->uuid('condominium_id');
            $table->string('name');
            $table->text('body');
            $table->uuid('media_storage_object_id')->nullable();
            $table->string('target_type', 20)->default('all'); // all | blocks | units
            $table->json('block_ids')->nullable();
            $table->json('unit_ids')->nullable();
            $table->integer('throttle_seconds')->default(10);   // intervalo entre envios (anti-ban)
            $table->string('status', 20)->default('draft');     // draft|scheduled|sending|completed|cancelled
            $table->timestamp('scheduled_at')->nullable();
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('skipped_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('connection_id')->references('id')->on('whatsapp_connections')->cascadeOnDelete();
            $table->foreign('condominium_id')->references('id')->on('condominiums')->cascadeOnDelete();
            $table->foreign('media_storage_object_id')->references('id')->on('storage_objects')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'status']);
        });

        // Destinatários de cada campanha (snapshot do telefone no momento da montagem).
        Schema::create('wa_campaign_recipients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('campaign_id');
            $table->uuid('person_id')->nullable();
            $table->string('name')->nullable();
            $table->string('phone');                         // dígitos com DDI
            $table->string('status', 20)->default('pending'); // pending|sent|failed|skipped
            $table->string('wa_message_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('campaign_id')->references('id')->on('wa_campaigns')->cascadeOnDelete();
            $table->foreign('person_id')->references('id')->on('persons')->nullOnDelete();
            $table->index(['campaign_id', 'status']);
        });

        // Lista de descadastro (opt-out) por telefone, por tenant.
        Schema::create('wa_opt_outs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('phone');                          // dígitos com DDI
            $table->string('reason')->nullable();             // ex.: "respondeu SAIR" | "manual"
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_opt_outs');
        Schema::dropIfExists('wa_campaign_recipients');
        Schema::dropIfExists('wa_campaigns');
    }
};
