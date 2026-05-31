<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('document', 18)->nullable(); // CNPJ
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('status', 20)->default('active'); // active, suspended, cancelled, trial
            $table->uuid('plan_id')->nullable();
            $table->jsonb('settings')->default('{}');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('plan_id')->references('id')->on('plans')->nullOnDelete();
            $table->index(['status']);
            $table->index(['slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
