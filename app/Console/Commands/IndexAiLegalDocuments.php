<?php

namespace App\Console\Commands;

use App\Models\AiLegalDocument;
use App\Services\AI\LegalDocumentIndexer;
use Illuminate\Console\Command;

class IndexAiLegalDocuments extends Command
{
    protected $signature = 'ai-legal-documents:index {--force : Reindexar mesmo os ja indexados}';

    protected $description = 'Indexa a base legal global para o RAG do assistente de IA.';

    public function handle(LegalDocumentIndexer $indexer): int
    {
        $query = AiLegalDocument::query()
            ->where('is_active', true)
            ->whereNotNull('storage_path');

        if (! $this->option('force')) {
            $query->whereDoesntHave('chunks');
        }

        $total = 0;
        $query->chunkById(50, function ($documents) use ($indexer, &$total) {
            foreach ($documents as $document) {
                $count = $indexer->index($document);
                $total += $count;
                $this->line("- {$document->title}: {$count} trecho(s)");
            }
        });

        $this->info("Concluido. {$total} trecho(s) legais indexado(s).");

        return self::SUCCESS;
    }
}
