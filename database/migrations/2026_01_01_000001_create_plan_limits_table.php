<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_limits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('plan_id');
            $table->string('resource', 100);
            $table->bigInteger('limit_value'); // -1 = ilimitado
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
            $table->unique(['plan_id', 'resource']);
            $table->index('plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_limits');
    }
};
