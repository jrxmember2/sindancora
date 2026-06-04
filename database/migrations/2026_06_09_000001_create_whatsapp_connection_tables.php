<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conexões WhatsApp (cada conexão = 1 instância Evolution = 1 número). Recurso licenciado.
        Schema::create('whatsapp_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');                       // rótulo dado pelo síndico
            $table->string('instance')->unique();         // nome da instância na Evolution
            $table->text('token')->nullable();            // token da instância (encrypted no model)
            $table->string('phone_number')->nullable();   // número conectado (após parear)
            $table->string('status', 20)->default('disconnected'); // disconnected | connecting | connected
            $table->boolean('bot_enabled')->default(false);
            $table->timestamp('last_connected_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'status']);
        });

        // Quais condomínios cada conexão atende (N:N). 1 número → 1 ou vários condomínios.
        Schema::create('whatsapp_connection_condominium', function (Blueprint $table) {
            $table->uuid('connection_id');
            $table->uuid('condominium_id');

            $table->foreign('connection_id')->references('id')->on('whatsapp_connections')->cascadeOnDelete();
            $table->foreign('condominium_id')->references('id')->on('condominiums')->cascadeOnDelete();
            $table->primary(['connection_id', 'condominium_id']);
            $table->index('condominium_id');
        });

        // Add-on: conexões avulsas vendidas além do limite do plano (espelha tenant_storage_addons).
        Schema::create('tenant_whatsapp_addons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->integer('quantity')->default(1);
            $table->decimal('price_paid', 10, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->uuid('added_by')->nullable();
            $table->string('reason')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('added_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_whatsapp_addons');
        Schema::dropIfExists('whatsapp_connection_condominium');
        Schema::dropIfExists('whatsapp_connections');
    }
};
