<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_legal_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_legal_documents', 'jurisdiction_level')) {
                $table->string('jurisdiction_level', 20)->default('general')->after('category');
            }
            if (! Schema::hasColumn('ai_legal_documents', 'state')) {
                $table->string('state', 2)->nullable()->after('jurisdiction_level');
            }
            if (! Schema::hasColumn('ai_legal_documents', 'city')) {
                $table->string('city', 120)->nullable()->after('state');
            }

            $table->index(['jurisdiction_level', 'state', 'city'], 'ai_legal_documents_jurisdiction_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_legal_documents', function (Blueprint $table) {
            $table->dropIndex('ai_legal_documents_jurisdiction_idx');
            $table->dropColumn(['jurisdiction_level', 'state', 'city']);
        });
    }
};
