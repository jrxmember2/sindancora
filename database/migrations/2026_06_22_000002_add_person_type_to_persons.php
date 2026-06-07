<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('persons', 'person_type')) {
            Schema::table('persons', function (Blueprint $table) {
                $table->string('person_type', 20)->default('individual')->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('persons', 'person_type')) {
            Schema::table('persons', function (Blueprint $table) {
                $table->dropColumn('person_type');
            });
        }
    }
};
