<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('occurrences', function (Blueprint $table) {
            $table->timestamp('due_at')->nullable()->after('status');             // prazo (SLA)
            $table->timestamp('first_response_at')->nullable()->after('due_at');  // 1ª resposta de gestor
            $table->timestamp('sla_notified_at')->nullable()->after('first_response_at'); // throttle do alerta
        });
    }

    public function down(): void
    {
        Schema::table('occurrences', function (Blueprint $table) {
            $table->dropColumn(['due_at', 'first_response_at', 'sla_notified_at']);
        });
    }
};
