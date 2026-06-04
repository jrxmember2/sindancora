<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pré-autorizações de visitantes (morador autoriza pelo portal; gestor pelo painel).
        Schema::create('visitor_authorizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('condominium_id');
            $table->uuid('unit_id');
            $table->uuid('created_by')->nullable();          // usuário que autorizou (morador/gestor)
            $table->string('visitor_name');
            $table->string('visitor_document')->nullable();
            $table->string('visitor_phone')->nullable();
            $table->string('type', 20)->default('single');   // single | recurring
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('token', 16)->unique();           // código apresentado/validado na portaria
            $table->string('status', 20)->default('active'); // active | used | expired | revoked
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('condominium_id')->references('id')->on('condominiums')->cascadeOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'condominium_id', 'status']);
        });

        // Log de acessos: entradas/saídas registradas pelo porteiro.
        Schema::create('visitor_visits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('condominium_id');
            $table->uuid('unit_id')->nullable();
            $table->uuid('authorization_id')->nullable();    // null = walk-in (registrado na hora)
            $table->string('visitor_name');
            $table->string('visitor_document')->nullable();
            $table->timestamp('check_in_at');
            $table->timestamp('check_out_at')->nullable();
            $table->uuid('registered_by')->nullable();       // porteiro que registrou
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('condominium_id')->references('id')->on('condominiums')->cascadeOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->nullOnDelete();
            $table->foreign('authorization_id')->references('id')->on('visitor_authorizations')->nullOnDelete();
            $table->foreign('registered_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'condominium_id', 'check_in_at']);
            $table->index(['condominium_id', 'check_out_at']); // visitantes presentes
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_visits');
        Schema::dropIfExists('visitor_authorizations');
    }
};
