<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('condominium_public_links')) {
            Schema::create('condominium_public_links', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('condominium_id');
                $table->string('token', 24)->unique();
                $table->boolean('active')->default(true);
                $table->boolean('allow_resident_signup')->default(true);
                $table->boolean('allow_occurrence')->default(true);
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('condominium_id')->references('id')->on('condominiums')->cascadeOnDelete();

                // Um link público por condomínio.
                $table->unique(['condominium_id']);
                $table->index(['tenant_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('condominium_public_links');
    }
};
