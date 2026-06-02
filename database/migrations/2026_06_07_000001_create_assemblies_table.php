<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assemblies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('condominium_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status', 20)->default('draft'); // draft | open | closed
            $table->text('minutes')->nullable();            // ata gerada
            $table->timestamp('minutes_generated_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('condominium_id')->references('id')->on('condominiums')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'condominium_id', 'status']);
        });

        Schema::create('assembly_agenda_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('assembly_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('assembly_id')->references('id')->on('assemblies')->cascadeOnDelete();
            $table->index(['assembly_id', 'position']);
        });

        Schema::create('assembly_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agenda_item_id');
            $table->string('label');
            $table->integer('position')->default(0);

            $table->foreign('agenda_item_id')->references('id')->on('assembly_agenda_items')->cascadeOnDelete();
            $table->index(['agenda_item_id', 'position']);
        });

        Schema::create('assembly_votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('assembly_id');
            $table->uuid('agenda_item_id');
            $table->uuid('option_id');
            $table->uuid('unit_id');
            $table->uuid('person_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('assembly_id')->references('id')->on('assemblies')->cascadeOnDelete();
            $table->foreign('agenda_item_id')->references('id')->on('assembly_agenda_items')->cascadeOnDelete();
            $table->foreign('option_id')->references('id')->on('assembly_options')->cascadeOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->foreign('person_id')->references('id')->on('persons')->nullOnDelete();
            // Um voto por unidade em cada item.
            $table->unique(['agenda_item_id', 'unit_id']);
        });

        Schema::create('assembly_attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('assembly_id');
            $table->uuid('unit_id');
            $table->uuid('person_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('assembly_id')->references('id')->on('assemblies')->cascadeOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->foreign('person_id')->references('id')->on('persons')->nullOnDelete();
            $table->unique(['assembly_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assembly_attendances');
        Schema::dropIfExists('assembly_votes');
        Schema::dropIfExists('assembly_options');
        Schema::dropIfExists('assembly_agenda_items');
        Schema::dropIfExists('assemblies');
    }
};
