<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->uuid('condominium_id')->nullable()->after('user_id');

            $table->foreign('condominium_id')->references('id')->on('condominiums')->nullOnDelete();
            $table->index(['tenant_id', 'condominium_id']);
        });

        Schema::table('ai_messages', function (Blueprint $table) {
            $table->json('sources')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->dropColumn('sources');
        });

        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropForeign(['condominium_id']);
            $table->dropIndex(['tenant_id', 'condominium_id']);
            $table->dropColumn('condominium_id');
        });
    }
};
