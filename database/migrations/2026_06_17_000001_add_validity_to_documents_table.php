<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vigência/validade de documentos (ex.: AVCB, alvarás, contratos) + alerta de vencimento.
        Schema::table('documents', function (Blueprint $table) {
            $table->date('valid_from')->nullable()->after('visibility');
            $table->date('valid_until')->nullable()->after('valid_from');
            $table->integer('renewal_alert_days')->nullable()->after('valid_until'); // dias antes do vencimento p/ alertar
            $table->timestamp('expiry_notified_at')->nullable()->after('renewal_alert_days'); // evita alerta duplicado
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['valid_from', 'valid_until', 'renewal_alert_days', 'expiry_notified_at']);
        });
    }
};
