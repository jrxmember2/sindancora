<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preferência do atendente: assinar as mensagens de WhatsApp com o próprio nome.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('sign_messages')->default(false)->after('status');
        });

        // Campanha: assinar o disparo com o nome de quem criou.
        Schema::table('wa_campaigns', function (Blueprint $table) {
            $table->boolean('sign')->default(false)->after('throttle_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sign_messages');
        });
        Schema::table('wa_campaigns', function (Blueprint $table) {
            $table->dropColumn('sign');
        });
    }
};
