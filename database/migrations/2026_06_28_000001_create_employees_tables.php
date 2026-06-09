<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('condominium_id');
                $table->uuid('person_id')->nullable();
                $table->uuid('created_by')->nullable();
                $table->string('name', 150);
                $table->string('document', 20)->nullable();
                $table->string('email', 150)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('position', 100)->nullable();
                $table->string('employment_type', 30)->default('clt');
                $table->string('status', 30)->default('active');
                $table->date('admission_date');
                $table->date('termination_date')->nullable();
                $table->string('ctps_number', 40)->nullable();
                $table->string('pis_pasep', 40)->nullable();
                $table->decimal('salary', 12, 2)->nullable();
                $table->integer('vacation_alert_days')->default(60);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('condominium_id')->references('id')->on('condominiums')->cascadeOnDelete();
                $table->foreign('person_id')->references('id')->on('persons')->nullOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

                $table->index(['tenant_id', 'condominium_id', 'status']);
                $table->index(['tenant_id', 'document']);
                $table->index(['tenant_id', 'admission_date']);
            });
        }

        if (! Schema::hasTable('employee_vacation_periods')) {
            Schema::create('employee_vacation_periods', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('employee_id');
                $table->date('acquisition_start');
                $table->date('acquisition_end');
                $table->date('deadline_date');
                $table->date('vacation_start')->nullable();
                $table->date('vacation_end')->nullable();
                $table->unsignedSmallInteger('days')->default(30);
                $table->string('status', 30)->default('pending');
                $table->timestamp('notified_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();

                $table->index(['tenant_id', 'status', 'deadline_date']);
                $table->index(['tenant_id', 'employee_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_vacation_periods');
        Schema::dropIfExists('employees');
    }
};
