<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Segredo do webhook da Evolution: vai na URL registrada e é conferido no recebimento,
        // impedindo que terceiros injetem mensagens falsas na inbox dos tenants.
        Schema::table('evolution_settings', function (Blueprint $table) {
            $table->string('webhook_secret', 64)->nullable()->after('webhook_url');
        });
    }

    public function down(): void
    {
        Schema::table('evolution_settings', function (Blueprint $table) {
            $table->dropColumn('webhook_secret');
        });
    }
};
