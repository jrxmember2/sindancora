<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\AI\DocumentIndexer;
use Illuminate\Console\Command;

class IndexDocuments extends Command
{
    protected $signature = 'documents:index {--tenant= : Limitar a um tenant} {--force : Reindexar mesmo os já indexados}';

    protected $description = 'Indexa o texto dos documentos (PDF/texto) para o RAG do assistente de IA.';

    public function handle(DocumentIndexer $indexer): int
    {
        // Sem contexto de tenant: o global scope não filtra (varre todos).
        $query = Document::query()->whereNotNull('storage_object_id')->with('storageObject');

        if ($tenant = $this->option('tenant')) {
            $query->where('tenant_id', $tenant);
        }

        if (! $this->option('force')) {
            $query->whereDoesntHave('chunks');
        }

        $total = 0;
        $query->chunkById(50, function ($documents) use ($indexer, &$total) {
            foreach ($documents as $document) {
                $count = $indexer->index($document);
                $total += $count;
                $this->line("• {$document->title}: {$count} trecho(s)");
            }
        });

        $this->info("Concluído. {$total} trecho(s) indexado(s).");

        return self::SUCCESS;
    }
}
