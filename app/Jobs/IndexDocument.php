<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\AI\DocumentIndexer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Indexa o texto de um documento para o RAG do assistente de IA (em fila — extração + chunking).
 */
class IndexDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public string $documentId) {}

    public function handle(DocumentIndexer $indexer): void
    {
        $document = Document::withoutGlobalScope('tenant')->find($this->documentId);

        if (! $document) {
            return;
        }

        try {
            $indexer->index($document);
        } catch (\Throwable $e) {
            Log::warning('Falha ao indexar documento para IA', ['document' => $this->documentId, 'error' => $e->getMessage()]);
        }
    }
}
