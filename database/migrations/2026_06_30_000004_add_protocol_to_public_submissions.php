<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('public_submissions') && ! Schema::hasColumn('public_submissions', 'protocol')) {
            Schema::table('public_submissions', function (Blueprint $table) {
                $table->string('protocol', 12)->nullable()->after('status');
                $table->index(['tenant_id', 'protocol']);
            });

            // Gera protocolo para envios já existentes.
            DB::table('public_submissions')->whereNull('protocol')->orderBy('id')->each(function ($row) {
                DB::table('public_submissions')->where('id', $row->id)->update([
                    'protocol' => Str::upper(Str::random(8)),
                ]);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('public_submissions', 'protocol')) {
            Schema::table('public_submissions', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'protocol']);
                $table->dropColumn('protocol');
            });
        }
    }
};
