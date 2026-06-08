<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;

class DocumentTextExtractor
{
    private const CHUNK_SIZE = 1200;
    private const CHUNK_OVERLAP = 150;

    public function extract(string $disk, string $path, string $mime, string $filename): string
    {
        try {
            $bytes = Storage::disk($disk)->get($path);
        } catch (\Throwable $e) {
            Log::warning('Indexacao: falha ao baixar documento', ['path' => $path, 'error' => $e->getMessage()]);

            return '';
        }

        if ($bytes === null) {
            return '';
        }

        $isPdf = str_contains($mime, 'pdf') || str_ends_with(strtolower($filename), '.pdf');

        if ($isPdf) {
            try {
                return $this->normalize((new PdfParser)->parseContent($bytes)->getText());
            } catch (\Throwable $e) {
                Log::info('Indexacao: PDF ilegivel', ['file' => $filename, 'error' => $e->getMessage()]);

                return '';
            }
        }

        $isText = str_starts_with($mime, 'text/') || preg_match('/\.(txt|md|csv)$/i', $filename);

        return $isText ? $this->normalize($bytes) : '';
    }

    /** @return array<int,string> */
    public function chunk(string $text): array
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

    private function normalize(string $text): string
    {
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim((string) $text);
    }
}
