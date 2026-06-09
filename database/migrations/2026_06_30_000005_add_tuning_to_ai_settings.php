<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_settings', 'temperature')) {
                $table->decimal('temperature', 3, 2)->default(0.30)->after('enabled');
            }
            if (! Schema::hasColumn('ai_settings', 'top_p')) {
                $table->decimal('top_p', 3, 2)->nullable()->after('temperature');
            }
            if (! Schema::hasColumn('ai_settings', 'max_tokens')) {
                $table->unsignedInteger('max_tokens')->default(2048)->after('top_p');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            foreach (['temperature', 'top_p', 'max_tokens'] as $column) {
                if (Schema::hasColumn('ai_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
