<?php

namespace App\Dashboard\Resolvers\Documents;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;
use App\Models\Document;

/**
 * Últimos documentos enviados. Timeline.
 */
class RecentDocumentsResolver extends BaseResolver
{
    public function resolve(DashboardContext $ctx): array
    {
        if ($ctx->hasNoScope()) {
            return $this->empty(['items' => []]);
        }

        $docs = $this->documentBase($ctx)
            ->with('condominium:id,name')
            ->latest()
            ->limit(6)
            ->get(['id', 'condominium_id', 'title', 'category', 'created_at']);

        $items = $docs->map(fn (Document $d) => [
            'title' => $d->title,
            'subtitle' => (Document::CATEGORIES[$d->category] ?? 'Documento').' · '.($d->condominium?->name ?? ''),
            'time' => $d->created_at?->format('d/m'),
            'color' => 'sky',
            'href' => '/documentos',
        ])->all();

        if ($items === []) {
            return $this->empty(['items' => [], 'emptyText' => 'Nenhum documento enviado ainda.']);
        }

        return ['items' => $items];
    }
}
