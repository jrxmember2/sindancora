<?php

namespace App\Services\AI;

use App\Models\DocumentChunk;
use Illuminate\Support\Facades\DB;

/**
 * Busca RAG por full-text (PostgreSQL) sobre os trechos de documentos do tenant.
 * Cai em ILIKE quando o full-text não está disponível (ex.: SQLite em testes).
 */
class DocumentSearch
{
    /**
     * @return array<int,array{title:string,content:string}>
     */
    public function search(string $tenantId, string $query, int $limit = 5): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        try {
            return $this->fullText($tenantId, $query, $limit);
        } catch (\Throwable) {
            return $this->ilike($tenantId, $query, $limit);
        }
    }

    private function fullText(string $tenantId, string $query, int $limit): array
    {
        return DocumentChunk::query()
            ->from('document_chunks as dc')
            ->join('documents as d', 'd.id', '=', 'dc.document_id')
            ->where('dc.tenant_id', $tenantId)
            ->whereNull('d.deleted_at')
            ->where('d.is_current', true)
            ->where('d.is_ai_searchable', true)
            ->whereRaw("to_tsvector('portuguese', dc.content) @@ plainto_tsquery('portuguese', ?)", [$query])
            ->orderByRaw("ts_rank(to_tsvector('portuguese', dc.content), plainto_tsquery('portuguese', ?)) DESC", [$query])
            ->limit($limit)
            ->get(['d.title as title', 'dc.content as content'])
            ->map(fn ($r) => ['title' => (string) $r->title, 'content' => (string) $r->content])
            ->all();
    }

    private function ilike(string $tenantId, string $query, int $limit): array
    {
        return DB::table('document_chunks as dc')
            ->join('documents as d', 'd.id', '=', 'dc.document_id')
            ->where('dc.tenant_id', $tenantId)
            ->whereNull('d.deleted_at')
            ->where('d.is_current', true)
            ->where('d.is_ai_searchable', true)
            ->where('dc.content', 'like', '%'.$query.'%')
            ->limit($limit)
            ->get(['d.title as title', 'dc.content as content'])
            ->map(fn ($r) => ['title' => (string) $r->title, 'content' => (string) $r->content])
            ->all();
    }
}
