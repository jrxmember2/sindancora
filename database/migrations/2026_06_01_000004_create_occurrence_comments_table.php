<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occurrence_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('occurrence_id');
            $table->uuid('user_id')->nullable();
            $table->string('type', 20)->default('comment'); // comment, status, assignment
            $table->text('body')->nullable();
            $table->json('meta')->nullable(); // ex.: {"from":"open","to":"in_progress"}
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('occurrence_id')->references('id')->on('occurrences')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'occurrence_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occurrence_comments');
    }
};
