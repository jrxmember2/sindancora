<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_legal_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 40)->default('other');
            $table->string('storage_provider', 50)->default('local');
            $table->string('storage_bucket', 255)->nullable();
            $table->string('storage_path', 1000)->nullable();
            $table->string('original_filename', 500)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->bigInteger('file_size_bytes')->nullable();
            $table->string('checksum_sha256', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('uploaded_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['category', 'is_active']);
        });

        Schema::create('ai_legal_document_chunks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ai_legal_document_id');
            $table->integer('chunk_index')->default(0);
            $table->text('content');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('ai_legal_document_id')->references('id')->on('ai_legal_documents')->cascadeOnDelete();
            $table->index(['ai_legal_document_id'], 'ai_legal_document_chunks_doc_idx');
        });

        try {
            DB::statement("CREATE INDEX ai_legal_document_chunks_fts_idx ON ai_legal_document_chunks USING GIN (to_tsvector('portuguese', content))");
        } catch (\Throwable) {
            // SQLite/MySQL em testes nao suportam tsvector; a busca cai em LIKE.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_legal_document_chunks');
        Schema::dropIfExists('ai_legal_documents');
    }
};
