<?php

namespace App\Jobs;

use App\Models\AiLegalDocument;
use App\Services\AI\LegalDocumentIndexer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IndexAiLegalDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public string $documentId)
    {
    }

    public function handle(LegalDocumentIndexer $indexer): void
    {
        $document = AiLegalDocument::find($this->documentId);

        if (! $document) {
            return;
        }

        try {
            $indexer->index($document);
        } catch (\Throwable $e) {
            Log::warning('Falha ao indexar documento legal para IA', [
                'document' => $this->documentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
