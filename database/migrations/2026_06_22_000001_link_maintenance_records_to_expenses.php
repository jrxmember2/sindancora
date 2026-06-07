<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('expenses', 'maintenance_record_id')) {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->uuid('maintenance_record_id')->nullable()->after('receipt_storage_object_id');

            $table->foreign('maintenance_record_id')
                ->references('id')
                ->on('maintenance_records')
                ->nullOnDelete();

            $table->unique(['tenant_id', 'maintenance_record_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('expenses', 'maintenance_record_id')) {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'maintenance_record_id']);
            $table->dropForeign(['maintenance_record_id']);
            $table->dropColumn('maintenance_record_id');
        });
    }
};
