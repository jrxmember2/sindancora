<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Achados & perdidos por condomínio: item achado ou perdido, com foto e status.
        Schema::create('lost_found_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('condominium_id');
            $table->string('type', 10)->default('found'); // found | lost
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('location')->nullable();
            $table->string('status', 20)->default('open'); // open | resolved
            $table->uuid('reported_by')->nullable();
            $table->date('occurred_on')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('condominium_id')->references('id')->on('condominiums')->cascadeOnDelete();
            $table->foreign('reported_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'condominium_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lost_found_items');
    }
};
