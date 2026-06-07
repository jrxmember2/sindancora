<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->uuid('supplier_id')->nullable()->after('supplier');
            $table->string('document_number', 80)->nullable()->after('supplier_id');
            $table->string('status', 20)->default('pending')->after('amount');
            $table->date('due_date')->nullable()->after('expense_date');
            $table->timestamp('paid_at')->nullable()->after('due_date');
            $table->decimal('paid_amount', 12, 2)->nullable()->after('paid_at');
            $table->string('payment_method', 30)->nullable()->after('paid_amount');
            $table->unsignedSmallInteger('reminder_days')->default(3)->after('document_number');
            $table->timestamp('reminder_sent_at')->nullable()->after('reminder_days');

            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->index(['tenant_id', 'status', 'due_date']);
            $table->index(['tenant_id', 'supplier_id']);
        });

        DB::table('expenses')->update([
            'status' => 'paid',
            'due_date' => DB::raw('expense_date'),
            'paid_at' => DB::raw('expense_date'),
            'paid_amount' => DB::raw('amount'),
        ]);
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropIndex(['tenant_id', 'status', 'due_date']);
            $table->dropIndex(['tenant_id', 'supplier_id']);

            $table->dropColumn([
                'supplier_id',
                'document_number',
                'status',
                'due_date',
                'paid_at',
                'paid_amount',
                'payment_method',
                'reminder_days',
                'reminder_sent_at',
            ]);
        });
    }
};
