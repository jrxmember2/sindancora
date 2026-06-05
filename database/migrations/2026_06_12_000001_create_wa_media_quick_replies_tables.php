<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mídia nas mensagens (Fase 4): tipo + arquivo armazenado no StorageService.
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->string('media_type', 20)->nullable()->after('body'); // image|video|audio|document|sticker
            $table->uuid('storage_object_id')->nullable()->after('media_type');

            $table->foreign('storage_object_id')->references('id')->on('storage_objects')->nullOnDelete();
        });

        // Respostas prontas (canned) — por tenant, opcionalmente específicas de um setor.
        Schema::create('wa_quick_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('sector_id')->nullable(); // null = disponível em todos os setores do tenant
            $table->string('title');
            $table->string('shortcut')->nullable();
            $table->text('body');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('sector_id')->references('id')->on('sectors')->nullOnDelete();
            $table->index(['tenant_id', 'sector_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_quick_replies');

        Schema::table('wa_messages', function (Blueprint $table) {
            $table->dropForeign(['storage_object_id']);
            $table->dropColumn(['media_type', 'storage_object_id']);
        });
    }
};
