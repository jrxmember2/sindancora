<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('works')) {
            Schema::create('works', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('condominium_id');
                $table->uuid('supplier_id')->nullable();
                $table->uuid('quotation_id')->nullable();
                $table->uuid('quotation_proposal_id')->nullable();
                $table->uuid('created_by')->nullable();
                $table->string('title', 160);
                $table->string('type', 40)->default('renovation');
                $table->string('status', 40)->default('planned');
                $table->string('priority', 20)->default('normal');
                $table->text('description')->nullable();
                $table->date('start_date')->nullable();
                $table->date('expected_end_date')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->decimal('budget_amount', 12, 2)->nullable();
                $table->decimal('final_amount', 12, 2)->nullable();
                $table->unsignedTinyInteger('progress_percent')->default(0);
                $table->string('responsible_name', 150)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('condominium_id')->references('id')->on('condominiums')->cascadeOnDelete();
                $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
                $table->foreign('quotation_id')->references('id')->on('quotations')->nullOnDelete();
                $table->foreign('quotation_proposal_id')->references('id')->on('quotation_proposals')->nullOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'condominium_id']);
                $table->index(['tenant_id', 'supplier_id']);
                $table->index(['tenant_id', 'quotation_proposal_id']);
            });
        }

        if (! Schema::hasTable('work_updates')) {
            Schema::create('work_updates', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('work_id');
                $table->uuid('user_id')->nullable();
                $table->string('title', 150);
                $table->text('description')->nullable();
                $table->string('status', 40)->nullable();
                $table->unsignedTinyInteger('progress_percent')->nullable();
                $table->timestamp('occurred_at')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('work_id')->references('id')->on('works')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

                $table->index(['tenant_id', 'work_id']);
                $table->index(['tenant_id', 'occurred_at']);
            });
        }

        if (Schema::hasTable('expenses') && ! Schema::hasColumn('expenses', 'work_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->uuid('work_id')->nullable();

                $table->foreign('work_id')->references('id')->on('works')->nullOnDelete();
                $table->index(['tenant_id', 'work_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'work_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'work_id']);
                $table->dropForeign(['work_id']);
                $table->dropColumn('work_id');
            });
        }

        Schema::dropIfExists('work_updates');
        Schema::dropIfExists('works');
    }
};
