<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\InteractsWithResident;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    use InteractsWithResident;

    public function index(Request $request): Response
    {
        $condominiumIds = $this->condominiumIds() ?: ['-'];

        $documents = Document::whereIn('condominium_id', $condominiumIds)
            ->where('visibility', 'residents')
            ->with(['condominium:id,name', 'storageObject:id,file_size_bytes,mime_type'])
            ->when($request->search, fn ($q, $s) => $q->where('title', 'ilike', "%{$s}%"))
            ->when($request->category, fn ($q, $c) => $q->where('category', $c))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Portal/Documents/Index', [
            'documents' => $documents,
            'categories' => Document::CATEGORIES,
            'filters' => $request->only(['search', 'category']),
        ]);
    }

    public function download(Document $document): RedirectResponse|StreamedResponse
    {
        abort_unless($document->tenant_id === app('tenant')->id, 403);
        abort_unless($document->visibility === 'residents', 403);
        abort_unless(in_array($document->condominium_id, $this->condominiumIds(), true), 403);

        $object = $document->storageObject;
        abort_unless($object, 404);

        $disk = Storage::disk($object->storage_provider);

        try {
            $url = $disk->temporaryUrl($object->storage_path, now()->addMinutes(10));

            return redirect()->away($url);
        } catch (\Throwable) {
            return $disk->download($object->storage_path, $object->original_filename);
        }
    }
}
