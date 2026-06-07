<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('condominium_id');
            $table->uuid('created_by')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->uuid('approved_proposal_id')->nullable();
            $table->string('category', 60)->nullable();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('collecting');
            $table->date('response_deadline')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('condominium_id')->references('id')->on('condominiums')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'condominium_id']);
        });

        Schema::create('quotation_proposals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('quotation_id');
            $table->uuid('supplier_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->string('supplier_name', 150);
            $table->decimal('amount', 12, 2);
            $table->integer('execution_days')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('status', 30)->default('received');
            $table->timestamp('submitted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('quotation_id')->references('id')->on('quotations')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['quotation_id', 'supplier_id']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->foreign('approved_proposal_id')
                ->references('id')
                ->on('quotation_proposals')
                ->nullOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->uuid('quotation_proposal_id')->nullable()->after('maintenance_record_id');
            $table->foreign('quotation_proposal_id')
                ->references('id')
                ->on('quotation_proposals')
                ->nullOnDelete();
            $table->index(['tenant_id', 'quotation_proposal_id']);
        });

        Schema::table('maintenance_plans', function (Blueprint $table) {
            $table->uuid('quotation_proposal_id')->nullable()->after('supplier_id');
            $table->foreign('quotation_proposal_id')
                ->references('id')
                ->on('quotation_proposals')
                ->nullOnDelete();
            $table->index(['tenant_id', 'quotation_proposal_id']);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('maintenance_plans', 'quotation_proposal_id')) {
            Schema::table('maintenance_plans', function (Blueprint $table) {
                $table->dropForeign(['quotation_proposal_id']);
                $table->dropIndex(['tenant_id', 'quotation_proposal_id']);
                $table->dropColumn('quotation_proposal_id');
            });
        }

        if (Schema::hasColumn('expenses', 'quotation_proposal_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropForeign(['quotation_proposal_id']);
                $table->dropIndex(['tenant_id', 'quotation_proposal_id']);
                $table->dropColumn('quotation_proposal_id');
            });
        }

        if (Schema::hasTable('quotations')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->dropForeign(['approved_proposal_id']);
            });
        }

        Schema::dropIfExists('quotation_proposals');
        Schema::dropIfExists('quotations');
    }
};
