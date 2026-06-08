<?php

namespace App\Services\AI;

use App\Models\AiLegalDocumentChunk;
use Illuminate\Support\Facades\DB;

class LegalDocumentSearch
{
    /**
     * @return array<int,array{title:string,category:string,content:string}>
     */
    public function search(string $query, int $limit = 5): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        try {
            return $this->fullText($query, $limit);
        } catch (\Throwable) {
            return $this->like($query, $limit);
        }
    }

    private function fullText(string $query, int $limit): array
    {
        return AiLegalDocumentChunk::query()
            ->from('ai_legal_document_chunks as lc')
            ->join('ai_legal_documents as ld', 'ld.id', '=', 'lc.ai_legal_document_id')
            ->whereNull('ld.deleted_at')
            ->where('ld.is_active', true)
            ->whereRaw("to_tsvector('portuguese', lc.content) @@ plainto_tsquery('portuguese', ?)", [$query])
            ->orderByRaw("ts_rank(to_tsvector('portuguese', lc.content), plainto_tsquery('portuguese', ?)) DESC", [$query])
            ->limit($limit)
            ->get(['ld.title as title', 'ld.category as category', 'lc.content as content'])
            ->map(fn ($r) => [
                'title' => (string) $r->title,
                'category' => (string) $r->category,
                'content' => (string) $r->content,
            ])
            ->all();
    }

    private function like(string $query, int $limit): array
    {
        return DB::table('ai_legal_document_chunks as lc')
            ->join('ai_legal_documents as ld', 'ld.id', '=', 'lc.ai_legal_document_id')
            ->whereNull('ld.deleted_at')
            ->where('ld.is_active', true)
            ->where('lc.content', 'like', '%'.$query.'%')
            ->limit($limit)
            ->get(['ld.title as title', 'ld.category as category', 'lc.content as content'])
            ->map(fn ($r) => [
                'title' => (string) $r->title,
                'category' => (string) $r->category,
                'content' => (string) $r->content,
            ])
            ->all();
    }
}
