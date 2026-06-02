<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('condominium_id');
            $table->uuid('created_by')->nullable();
            $table->string('title');
            $table->longText('body'); // HTML do editor rico (TipTap)
            $table->string('category', 30)->default('general');
            $table->string('urgency', 20)->default('normal'); // low, normal, high
            $table->string('status', 20)->default('draft');    // draft, published
            $table->timestamp('published_at')->nullable(); // quando foi efetivamente publicado
            $table->timestamp('publish_at')->nullable();   // agendamento (publicar a partir de)
            $table->timestamp('expires_at')->nullable();   // expiração automática
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('condominium_id')->references('id')->on('condominiums')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'condominium_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
