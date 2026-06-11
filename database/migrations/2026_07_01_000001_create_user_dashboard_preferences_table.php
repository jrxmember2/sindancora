<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_dashboard_preferences')) {
            Schema::create('user_dashboard_preferences', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id')->nullable();
                $table->uuid('user_id');
                // Chaves de widgets ocultados pelo usuário.
                $table->json('hidden_widgets')->nullable();
                // Ordem personalizada das chaves de widget (sobrepõe a ordem padrão do registry).
                $table->json('widget_order')->nullable();
                // Filtros persistidos (período, condomínio, status).
                $table->json('filters')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->unique(['tenant_id', 'user_id'], 'user_dashboard_preferences_unique');
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_dashboard_preferences');
    }
};
