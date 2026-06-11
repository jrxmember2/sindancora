<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enquetes rápidas por condomínio: consulta leve aos moradores (1 voto por pessoa),
        // fora do rito de assembleia.
        Schema::create('polls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('condominium_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft'); // draft | open | closed
            $table->boolean('is_anonymous')->default(false);
            $table->timestamp('closes_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('condominium_id')->references('id')->on('condominiums')->cascadeOnDelete();
            $table->index(['tenant_id', 'condominium_id', 'status']);
        });

        Schema::create('poll_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('poll_id');
            $table->string('label');
            $table->integer('sort_order')->default(0);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('poll_id')->references('id')->on('polls')->cascadeOnDelete();
            $table->index('poll_id');
        });

        Schema::create('poll_votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('poll_id');
            $table->uuid('option_id');
            $table->uuid('person_id');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('poll_id')->references('id')->on('polls')->cascadeOnDelete();
            $table->foreign('option_id')->references('id')->on('poll_options')->cascadeOnDelete();
            $table->foreign('person_id')->references('id')->on('persons')->cascadeOnDelete();
            $table->unique(['poll_id', 'person_id']); // 1 voto por pessoa
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('poll_options');
        Schema::dropIfExists('polls');
    }
};
