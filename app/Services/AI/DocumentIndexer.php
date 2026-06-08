<?php

namespace App\Services\AI;

use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Support\Facades\DB;

/**
 * Extrai o texto de um documento do tenant, divide em trechos e grava em document_chunks.
 */
class DocumentIndexer
{
    public function __construct(private readonly DocumentTextExtractor $extractor)
    {
    }

    public function index(Document $document): int
    {
        if (! $document->isSearchableByAi()) {
            DocumentChunk::where('document_id', $document->id)->delete();

            return 0;
        }

        $object = $document->storageObject;
        if (! $object) {
            return 0;
        }

        $text = $this->extractor->extract(
            $object->storage_provider,
            $object->storage_path,
            (string) $object->mime_type,
            (string) $object->original_filename,
        );

        DocumentChunk::where('document_id', $document->id)->delete();

        if ($text === '') {
            return 0;
        }

        $rows = [];
        $now = now();
        foreach ($this->extractor->chunk($text) as $i => $content) {
            $rows[] = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'tenant_id' => $document->tenant_id,
                'document_id' => $document->id,
                'condominium_id' => $document->condominium_id,
                'chunk_index' => $i,
                'content' => $content,
                'created_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 100) as $batch) {
            DB::table('document_chunks')->insert($batch);
        }

        return count($rows);
    }
}
