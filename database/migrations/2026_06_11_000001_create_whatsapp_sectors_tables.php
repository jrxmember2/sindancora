<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Setores de atendimento por condomínio (Portaria, ADM, etc.) — destino do roteamento do chatbot.
        Schema::create('sectors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('condominium_id');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->json('office_hours')->nullable();      // { mon: {enabled, open, close}, ... }
            $table->text('away_message')->nullable();        // mensagem de fora de expediente
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('condominium_id')->references('id')->on('condominiums')->cascadeOnDelete();
            $table->index(['tenant_id', 'condominium_id', 'is_active']);
        });

        // Membros (atendentes) de cada setor — define o escopo de quem vê as conversas na inbox.
        Schema::create('sector_user', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sector_id');
            $table->uuid('user_id');
            $table->timestamps();

            $table->foreign('sector_id')->references('id')->on('sectors')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['sector_id', 'user_id']);
        });

        // Mensagens configuráveis do chatbot, por condomínio (saudação, cabeçalhos de menu, opção inválida).
        Schema::create('whatsapp_bot_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('condominium_id');
            $table->boolean('is_enabled')->default(true);
            $table->text('greeting_message')->nullable();
            $table->text('sector_menu_header')->nullable();
            $table->text('invalid_option_message')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('condominium_id')->references('id')->on('condominiums')->cascadeOnDelete();
            $table->unique('condominium_id');
        });

        // Cabeçalho do menu de seleção de condomínio (só usado quando a conexão atende >1 condomínio).
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            $table->text('condominium_menu_header')->nullable()->after('bot_enabled');
        });

        // Estado do chatbot por conversa + setor de destino após o roteamento.
        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->uuid('sector_id')->nullable()->after('condominium_id');
            $table->string('bot_state', 30)->default('new')->after('status'); // new|awaiting_condominium|awaiting_sector|routed

            $table->foreign('sector_id')->references('id')->on('sectors')->nullOnDelete();
            $table->index('sector_id');
        });
    }

    public function down(): void
    {
        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->dropForeign(['sector_id']);
            $table->dropColumn(['sector_id', 'bot_state']);
        });

        Schema::table('whatsapp_connections', function (Blueprint $table) {
            $table->dropColumn('condominium_menu_header');
        });

        Schema::dropIfExists('whatsapp_bot_settings');
        Schema::dropIfExists('sector_user');
        Schema::dropIfExists('sectors');
    }
};
