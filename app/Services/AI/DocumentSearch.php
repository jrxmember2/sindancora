<?php

namespace App\Services\AI;

use App\Models\DocumentChunk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Busca RAG sobre os trechos de documentos do tenant. Usa full-text do PostgreSQL com semântica
 * OR (qualquer termo) e ranqueamento por relevância — diferente do AND estrito do plainto_tsquery,
 * que perdia trechos quando a pergunta usava palavras que não co-ocorriam no mesmo trecho. Cai em
 * ILIKE por termo quando o full-text não está disponível (ex.: SQLite em testes).
 */
class DocumentSearch
{
    /**
     * @return array<int,array{id:string,title:string,category:string,content:string}>
     */
    public function search(string $tenantId, string $query, int $limit = 10, ?string $condominiumId = null): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        try {
            $rows = $this->fullText($tenantId, $query, $limit, $condominiumId);
            if ($rows !== []) {
                return $rows;
            }
        } catch (\Throwable) {
            // segue para o fallback
        }

        return $this->ilike($tenantId, $query, $limit, $condominiumId);
    }

    private function fullText(string $tenantId, string $query, int $limit, ?string $condominiumId): array
    {
        // Converte o AND do plainto_tsquery em OR (a & b & c → a | b | c) para recall maior;
        // o ts_rank cuida de priorizar trechos que casam mais termos.
        $orTsquery = "replace(plainto_tsquery('portuguese', ?)::text, '&', '|')::tsquery";

        return DocumentChunk::query()
            ->from('document_chunks as dc')
            ->join('documents as d', 'd.id', '=', 'dc.document_id')
            ->where('dc.tenant_id', $tenantId)
            ->when($condominiumId, fn ($q, $id) => $q->where('d.condominium_id', $id))
            ->whereNull('d.deleted_at')
            ->where('d.is_current', true)
            ->where('d.is_ai_searchable', true)
            ->whereRaw("plainto_tsquery('portuguese', ?) <> ''::tsquery", [$query])
            ->whereRaw("to_tsvector('portuguese', dc.content) @@ {$orTsquery}", [$query])
            ->orderByRaw("ts_rank(to_tsvector('portuguese', dc.content), {$orTsquery}) DESC", [$query])
            ->limit($limit)
            ->get(['d.id as id', 'd.title as title', 'd.category as category', 'dc.content as content'])
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'title' => (string) $r->title,
                'category' => (string) $r->category,
                'content' => (string) $r->content,
            ])
            ->all();
    }

    private function ilike(string $tenantId, string $query, int $limit, ?string $condominiumId): array
    {
        // Termos significativos (>= 3 letras) combinados por OR.
        $terms = collect(preg_split('/\s+/', Str::lower($query)))
            ->map(fn ($t) => trim((string) $t, " \t\n\r\0\x0B.,;:!?\"'()[]"))
            ->filter(fn ($t) => Str::length($t) >= 3)
            ->unique()
            ->take(12)
            ->values();

        if ($terms->isEmpty()) {
            $terms = collect([$query]);
        }

        return DB::table('document_chunks as dc')
            ->join('documents as d', 'd.id', '=', 'dc.document_id')
            ->where('dc.tenant_id', $tenantId)
            ->when($condominiumId, fn ($q, $id) => $q->where('d.condominium_id', $id))
            ->whereNull('d.deleted_at')
            ->where('d.is_current', true)
            ->where('d.is_ai_searchable', true)
            ->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    $q->orWhere('dc.content', 'like', '%'.$term.'%');
                }
            })
            ->limit($limit)
            ->get(['d.id as id', 'd.title as title', 'd.category as category', 'dc.content as content'])
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'title' => (string) $r->title,
                'category' => (string) $r->category,
                'content' => (string) $r->content,
            ])
            ->all();
    }
}
