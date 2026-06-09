<?php

namespace App\Services\AI;

use App\Models\Condominium;
use App\Models\AiLegalDocumentChunk;
use Illuminate\Support\Facades\DB;

class LegalDocumentSearch
{
    /**
     * @return array<int,array{id:string,title:string,category:string,jurisdiction_level:string,state:?string,city:?string,content:string}>
     */
    public function search(string $query, int $limit = 5, ?Condominium $condominium = null): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        try {
            return $this->fullText($query, $limit, $condominium);
        } catch (\Throwable) {
            return $this->like($query, $limit, $condominium);
        }
    }

    private function fullText(string $query, int $limit, ?Condominium $condominium): array
    {
        return AiLegalDocumentChunk::query()
            ->from('ai_legal_document_chunks as lc')
            ->join('ai_legal_documents as ld', 'ld.id', '=', 'lc.ai_legal_document_id')
            ->whereNull('ld.deleted_at')
            ->where('ld.is_active', true)
            ->where(fn ($q) => $this->applyJurisdiction($q, $condominium))
            ->whereRaw("to_tsvector('portuguese', lc.content) @@ plainto_tsquery('portuguese', ?)", [$query])
            ->orderByRaw("ts_rank(to_tsvector('portuguese', lc.content), plainto_tsquery('portuguese', ?)) DESC", [$query])
            ->limit($limit)
            ->get([
                'ld.id as id',
                'ld.title as title',
                'ld.category as category',
                'ld.jurisdiction_level as jurisdiction_level',
                'ld.state as state',
                'ld.city as city',
                'lc.content as content',
            ])
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'title' => (string) $r->title,
                'category' => (string) $r->category,
                'jurisdiction_level' => (string) ($r->jurisdiction_level ?: 'general'),
                'state' => $r->state ? (string) $r->state : null,
                'city' => $r->city ? (string) $r->city : null,
                'content' => (string) $r->content,
            ])
            ->all();
    }

    private function like(string $query, int $limit, ?Condominium $condominium): array
    {
        return DB::table('ai_legal_document_chunks as lc')
            ->join('ai_legal_documents as ld', 'ld.id', '=', 'lc.ai_legal_document_id')
            ->whereNull('ld.deleted_at')
            ->where('ld.is_active', true)
            ->where(fn ($q) => $this->applyJurisdiction($q, $condominium))
            ->where('lc.content', 'like', '%'.$query.'%')
            ->limit($limit)
            ->get([
                'ld.id as id',
                'ld.title as title',
                'ld.category as category',
                'ld.jurisdiction_level as jurisdiction_level',
                'ld.state as state',
                'ld.city as city',
                'lc.content as content',
            ])
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'title' => (string) $r->title,
                'category' => (string) $r->category,
                'jurisdiction_level' => (string) ($r->jurisdiction_level ?: 'general'),
                'state' => $r->state ? (string) $r->state : null,
                'city' => $r->city ? (string) $r->city : null,
                'content' => (string) $r->content,
            ])
            ->all();
    }

    private function applyJurisdiction($query, ?Condominium $condominium): void
    {
        $state = strtoupper((string) $condominium?->state);
        $city = trim((string) $condominium?->city);

        $query->where(function ($scope) use ($state, $city) {
            $scope->whereNull('ld.jurisdiction_level')
                ->orWhereIn('ld.jurisdiction_level', ['general', 'federal']);

            if ($state !== '') {
                $scope->orWhere(function ($stateQuery) use ($state) {
                    $stateQuery->where('ld.jurisdiction_level', 'state')
                        ->where('ld.state', $state);
                });
            }

            if ($state !== '' && $city !== '') {
                $scope->orWhere(function ($cityQuery) use ($state, $city) {
                    $cityQuery->where('ld.jurisdiction_level', 'municipal')
                        ->where('ld.state', $state)
                        ->whereRaw('lower(ld.city) = lower(?)', [$city]);
                });
            }
        });
    }
}
