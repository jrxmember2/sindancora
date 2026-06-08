<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->boolean('is_current')->default(true)->after('renewal_alert_days');
            $table->boolean('is_ai_searchable')->default(true)->after('is_current');
            $table->index(['tenant_id', 'is_current', 'is_ai_searchable'], 'documents_ai_search_idx');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('documents_ai_search_idx');
            $table->dropColumn(['is_current', 'is_ai_searchable']);
        });
    }
};
