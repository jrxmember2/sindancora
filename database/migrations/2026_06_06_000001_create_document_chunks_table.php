<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('document_id');
            $table->uuid('condominium_id')->nullable();
            $table->integer('chunk_index')->default(0);
            $table->text('content');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->index(['tenant_id', 'document_id']);
        });

        // Índice full-text (português) para o RAG. Em bancos não-Postgres (testes), ignora.
        try {
            DB::statement("CREATE INDEX document_chunks_fts_idx ON document_chunks USING GIN (to_tsvector('portuguese', content))");
        } catch (\Throwable) {
            // SQLite/MySQL em testes não suportam tsvector — busca cai em ilike no serviço.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
