<?php

namespace App\Services\AI;

use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Extrai o texto de um documento (PDF/texto), divide em trechos e grava em document_chunks
 * para a busca full-text do RAG. Reindexa de forma idempotente (apaga os trechos antigos).
 */
class DocumentIndexer
{
    private const CHUNK_SIZE = 1200;   // caracteres por trecho
    private const CHUNK_OVERLAP = 150; // sobreposição entre trechos

    public function index(Document $document): int
    {
        $object = $document->storageObject;
        if (! $object) {
            return 0;
        }

        $text = $this->extractText($object->storage_provider, $object->storage_path, (string) $object->mime_type, (string) $object->original_filename);
        $text = $this->normalize($text);

        // Substitui os trechos existentes (reindexação).
        DocumentChunk::where('document_id', $document->id)->delete();

        if ($text === '') {
            return 0;
        }

        $chunks = $this->chunk($text);
        $now = now();
        $rows = [];
        foreach ($chunks as $i => $content) {
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

    private function extractText(string $disk, string $path, string $mime, string $filename): string
    {
        try {
            $bytes = Storage::disk($disk)->get($path);
        } catch (\Throwable $e) {
            Log::warning('Indexação: falha ao baixar documento', ['path' => $path, 'error' => $e->getMessage()]);

            return '';
        }

        if ($bytes === null) {
            return '';
        }

        $isPdf = str_contains($mime, 'pdf') || str_ends_with(strtolower($filename), '.pdf');

        if ($isPdf) {
            try {
                return (new PdfParser)->parseContent($bytes)->getText();
            } catch (\Throwable $e) {
                Log::info('Indexação: PDF ilegível', ['file' => $filename, 'error' => $e->getMessage()]);

                return '';
            }
        }

        // Texto simples (txt, md, csv...). Outros binários (docx/xlsx) ficam fora desta fatia.
        $isText = str_starts_with($mime, 'text/') || preg_match('/\.(txt|md|csv)$/i', $filename);

        return $isText ? $bytes : '';
    }

    private function normalize(string $text): string
    {
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim((string) $text);
    }

    /** @return array<int,string> */
    private function chunk(string $text): array
    {
        $chunks = [];
        $length = mb_strlen($text);
        $start = 0;

        while ($start < $length) {
            $chunks[] = mb_substr($text, $start, self::CHUNK_SIZE);
            $start += self::CHUNK_SIZE - self::CHUNK_OVERLAP;
        }

        return $chunks;
    }
}
