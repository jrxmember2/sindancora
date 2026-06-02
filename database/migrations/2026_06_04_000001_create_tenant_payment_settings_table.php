<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_payment_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('provider', 30)->default('asaas');     // gateway (asaas; multi-gateway futuro)
            $table->string('environment', 20)->default('sandbox'); // sandbox | production
            $table->text('api_key')->nullable();                   // criptografada (cast encrypted no model)
            $table->string('wallet_id')->nullable();               // split/repasse futuro
            $table->string('webhook_token')->nullable();           // valida o header asaas-access-token
            $table->string('billing_type', 20)->default('UNDEFINED'); // UNDEFINED = boleto + PIX
            $table->boolean('enabled')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_payment_settings');
    }
};
