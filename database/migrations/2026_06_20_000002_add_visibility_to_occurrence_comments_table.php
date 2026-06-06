<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('occurrence_comments', function (Blueprint $table) {
            // Acompanhamento interno (só gestores) vs público (morador vê no portal).
            $table->boolean('is_internal')->default(false)->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('occurrence_comments', function (Blueprint $table) {
            $table->dropColumn('is_internal');
        });
    }
};
