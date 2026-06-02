<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Liga o usuário do portal à pessoa (morador) já cadastrada na Fase 2.
            // Nullable: usuários administrativos (admin/síndico) podem não ter Person vinculada.
            $table->uuid('person_id')->nullable()->after('tenant_id');

            $table->foreign('person_id')->references('id')->on('persons')->nullOnDelete();
            $table->index(['tenant_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->dropIndex(['tenant_id', 'person_id']);
            $table->dropColumn('person_id');
        });
    }
};
