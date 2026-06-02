<?php

namespace App\Http\Controllers\Panel;

use App\Exceptions\StorageQuotaException;
use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Models\Document;
use App\Services\StorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(private readonly StorageService $storage)
    {
    }

    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $documents = Document::where('tenant_id', $tenant->id)
            ->with(['condominium:id,name', 'storageObject', 'uploader:id,name'])
            ->when($request->search, fn ($q, $s) => $q->where('title', 'ilike', "%{$s}%"))
            ->when($request->category, fn ($q, $c) => $q->where('category', $c))
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Documents/Index', [
            'documents' => $documents,
            'condominiums' => $this->condominiumOptions($tenant->id),
            'categories' => Document::CATEGORIES,
            'visibilities' => Document::VISIBILITIES,
            'usage' => $this->storage->getUsageStats($tenant),
            'filters' => $request->only(['search', 'category', 'condominium_id']),
        ]);
    }

    public function create(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Documents/Create', [
            'condominiums' => $this->condominiumOptions($tenant->id),
            'categories' => Document::CATEGORIES,
            'visibilities' => Document::VISIBILITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $this->validated($request, $tenant->id, withFile: true);

        $document = Document::create([
            'tenant_id' => $tenant->id,
            'condominium_id' => $data['condominium_id'],
            'uploaded_by' => Auth::id(),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'],
            'visibility' => $data['visibility'],
        ]);

        try {
            $object = $this->storage->upload(
                file: $request->file('file'),
                tenant: $tenant,
                entityType: 'document',
                entityId: $document->id,
                visibility: Document::STORAGE_VISIBILITY[$data['visibility']] ?? 'tenant',
                condominiumId: $data['condominium_id'],
            );
        } catch (StorageQuotaException $e) {
            // Desfaz o registro de domínio se o arquivo não pôde ser armazenado.
            $document->forceDelete();

            return back()->withErrors(['file' => $e->getMessage()])->withInput();
        }

        $document->update(['storage_object_id' => $object->id]);

        return redirect()->route('documents.index')->with('success', "Documento \"{$document->title}\" enviado.");
    }

    public function edit(Document $document): Response
    {
        $document = $this->authorizeTenant($document);
        $document->load('storageObject', 'condominium:id,name');

        return Inertia::render('Documents/Edit', [
            'document' => $document,
            'condominiums' => $this->condominiumOptions($document->tenant_id),
            'categories' => Document::CATEGORIES,
            'visibilities' => Document::VISIBILITIES,
        ]);
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        $document = $this->authorizeTenant($document);
        $data = $this->validated($request, $document->tenant_id, withFile: false);

        $document->update($data);

        // Mantém a visibilidade do arquivo em storage coerente com a do documento.
        if ($document->storageObject) {
            $document->storageObject->update([
                'visibility' => Document::STORAGE_VISIBILITY[$data['visibility']] ?? 'tenant',
            ]);
        }

        return redirect()->route('documents.index')->with('success', 'Documento atualizado.');
    }

    public function destroy(Document $document): RedirectResponse
    {
        $document = $this->authorizeTenant($document);

        // Soft delete: arquivo vai para a lixeira (remoção definitiva em 30 dias, ver StorageService).
        if ($document->storageObject) {
            $this->storage->delete($document->storageObject);
        }
        $document->delete();

        return redirect()->route('documents.index')->with('success', 'Documento removido.');
    }

    public function download(Document $document): RedirectResponse|StreamedResponse
    {
        $document = $this->authorizeTenant($document);
        $object = $document->storageObject;
        abort_unless($object, 404);

        $disk = Storage::disk($object->storage_provider);

        // Disks S3/R2/MinIO suportam URL assinada temporária; local cai no streaming.
        try {
            $url = $disk->temporaryUrl($object->storage_path, now()->addMinutes(10));

            return redirect()->away($url);
        } catch (\Throwable) {
            return $disk->download($object->storage_path, $object->original_filename);
        }
    }

    private function validated(Request $request, string $tenantId, bool $withFile): array
    {
        $rules = [
            'condominium_id' => "required|uuid|exists:condominiums,id,tenant_id,{$tenantId}",
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'category' => 'required|in:'.implode(',', array_keys(Document::CATEGORIES)),
            'visibility' => 'required|in:'.implode(',', array_keys(Document::VISIBILITIES)),
        ];

        if ($withFile) {
            $rules['file'] = 'required|file|max:51200|mimes:pdf,doc,docx,xls,xlsx,odt,ods,jpg,jpeg,png,webp,gif,zip';
        }

        return $request->validate($rules);
    }

    private function authorizeTenant(Document $document): Document
    {
        abort_unless($document->tenant_id === app('tenant')->id, 403);

        return $document;
    }

    private function condominiumOptions(string $tenantId): \Illuminate\Support\Collection
    {
        return Condominium::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]);
    }
}
