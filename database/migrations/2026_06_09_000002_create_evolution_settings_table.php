<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Config GLOBAL do servidor Evolution (uma linha; nível plataforma / super admin).
        // Não tem tenant_id — é a conexão da SindÂncora com o servidor Evolution auto-hospedado.
        Schema::create('evolution_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('base_url')->nullable();
            $table->text('api_key')->nullable();        // chave global do servidor (encrypted no model)
            $table->string('webhook_url')->nullable();  // URL pública de recebimento (Fase 2)
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evolution_settings');
    }
};
