<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Config GLOBAL de IA da plataforma. Nao tem tenant_id: a chave e o provedor
        // sao definidos pelo superadmin e usados pelos tenants com modulo contratado.
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider', 40)->default('anthropic');
            $table->string('model')->nullable();
            $table->string('base_url')->nullable();
            $table->text('api_key')->nullable(); // encrypted no model
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
