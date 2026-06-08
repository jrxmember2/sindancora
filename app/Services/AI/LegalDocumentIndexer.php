<?php

namespace App\Services\AI;

use App\Models\AiLegalDocument;
use App\Models\AiLegalDocumentChunk;
use Illuminate\Support\Facades\DB;

class LegalDocumentIndexer
{
    public function __construct(private readonly DocumentTextExtractor $extractor)
    {
    }

    public function index(AiLegalDocument $document): int
    {
        if (! $document->is_active) {
            AiLegalDocumentChunk::where('ai_legal_document_id', $document->id)->delete();

            return 0;
        }

        if (! $document->storage_path) {
            return 0;
        }

        $text = $this->extractor->extract(
            $document->storage_provider,
            $document->storage_path,
            (string) $document->mime_type,
            (string) $document->original_filename,
        );

        AiLegalDocumentChunk::where('ai_legal_document_id', $document->id)->delete();

        if ($text === '') {
            return 0;
        }

        $rows = [];
        $now = now();
        foreach ($this->extractor->chunk($text) as $i => $content) {
            $rows[] = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'ai_legal_document_id' => $document->id,
                'chunk_index' => $i,
                'content' => $content,
                'created_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 100) as $batch) {
            DB::table('ai_legal_document_chunks')->insert($batch);
        }

        return count($rows);
    }
}
