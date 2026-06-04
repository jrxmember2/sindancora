<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conversas de WhatsApp (uma por conexão + contato).
        Schema::create('wa_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('connection_id');
            $table->uuid('condominium_id')->nullable();  // resolvido só quando a conexão atende 1 condo (Fase 2)
            $table->string('contact_phone');             // dígitos com DDI (jid do WhatsApp)
            $table->string('contact_name')->nullable();
            $table->string('status', 20)->default('open'); // open | closed
            $table->uuid('assigned_to')->nullable();
            $table->integer('unread_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('connection_id')->references('id')->on('whatsapp_connections')->cascadeOnDelete();
            $table->foreign('condominium_id')->references('id')->on('condominiums')->nullOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->unique(['connection_id', 'contact_phone']);
            $table->index(['tenant_id', 'condominium_id', 'status']);
            $table->index('last_message_at');
        });

        // Mensagens das conversas.
        Schema::create('wa_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('conversation_id');
            $table->string('direction', 3);              // in | out
            $table->text('body')->nullable();
            $table->string('wa_message_id')->nullable();  // id na Evolution/WhatsApp (dedupe)
            $table->uuid('sent_by')->nullable();          // atendente, quando saída
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('conversation_id')->references('id')->on('wa_conversations')->cascadeOnDelete();
            $table->foreign('sent_by')->references('id')->on('users')->nullOnDelete();
            $table->unique('wa_message_id');
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_messages');
        Schema::dropIfExists('wa_conversations');
    }
};
