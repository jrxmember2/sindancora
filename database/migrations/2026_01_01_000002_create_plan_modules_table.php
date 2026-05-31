<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('plan_id');
            $table->string('module', 100);
            $table->boolean('enabled')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
            $table->unique(['plan_id', 'module']);
            $table->index('plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_modules');
    }
};
