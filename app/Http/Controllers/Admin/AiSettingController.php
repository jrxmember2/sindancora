<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\IndexAiLegalDocument;
use App\Models\AiLegalDocument;
use App\Models\AiLegalDocumentChunk;
use App\Models\AiSetting;
use App\Services\AI\AiException;
use App\Services\AI\AiModelCatalog;
use App\Services\AI\AiProviderManager;
use App\Services\AI\AiSettingsManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiSettingController extends Controller
{
    public function __construct(
        private readonly AiSettingsManager $settings,
        private readonly AiProviderManager $provider,
        private readonly AiModelCatalog $models,
    ) {}

    public function edit(): Response
    {
        $setting = AiSetting::first();
        $provider = $setting?->provider ?: AiSetting::defaultProvider();
        $defaults = $this->settings->defaults();

        return Inertia::render('Admin/AI/Settings', [
            'setting' => [
                'provider' => $provider,
                'model' => $setting?->model ?: data_get($defaults, "{$provider}.model"),
                'base_url' => $setting?->base_url ?: data_get($defaults, "{$provider}.base_url"),
                'enabled' => $setting?->enabled ?? true,
                'has_key' => filled($setting?->api_key) || filled($this->settings->apiKey()),
                'last_checked_at' => $setting?->last_checked_at?->toIso8601String(),
            ],
            'configured' => $this->settings->isConfigured(),
            'runtimeSupported' => $this->settings->runtimeSupported(),
            'providerOptions' => AiSetting::providerOptions(),
            'modelOptions' => $this->models->options(),
            'defaults' => $defaults,
            'legalDocuments' => $this->legalDocumentsPayload(),
            'legalCategories' => AiLegalDocument::CATEGORIES,
            'legalJurisdictions' => AiLegalDocument::JURISDICTIONS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $providers = array_keys(AiSetting::providerOptions());
        $provider = (string) $request->input('provider');
        $modelIds = array_column($this->models->options()[$provider] ?? [], 'value');

        $data = $request->validate([
            'provider' => ['required', 'string', Rule::in($providers)],
            'model' => ['required', 'string', 'max:120', Rule::in($modelIds)],
            'base_url' => 'nullable|url|max:255',
            'api_key' => 'nullable|string|max:4000',
            'enabled' => 'boolean',
        ], [
            'model.required' => 'Selecione um modelo disponivel para o provedor escolhido.',
            'model.in' => 'Selecione um modelo disponivel para o provedor escolhido.',
        ]);

        $setting = AiSetting::current();
        $defaults = $this->settings->defaults();
        $provider = $data['provider'];
        $previousProvider = $setting->provider;

        $setting->provider = $provider;
        $setting->model = filled($data['model'] ?? null)
            ? $data['model']
            : data_get($defaults, "{$provider}.model");
        $setting->base_url = filled($data['base_url'] ?? null)
            ? $data['base_url']
            : data_get($defaults, "{$provider}.base_url");
        $setting->enabled = (bool) ($data['enabled'] ?? false);

        // Campo write-only: em branco mantem a chave ja salva apenas se o provedor nao mudou.
        if (filled($data['api_key'] ?? null)) {
            $setting->api_key = $data['api_key'];
        } elseif ($previousProvider !== $provider) {
            $setting->api_key = null;
        }

        $setting->save();

        return back()->with('success', 'Configuracao global de IA salva.');
    }

    public function test(): RedirectResponse
    {
        $setting = AiSetting::current();

        try {
            $this->provider->complete(
                'Voce esta testando a conexao de IA da plataforma. Responda exatamente OK.',
                [['role' => 'user', 'content' => 'Teste de conexao.']],
                128,
            );
        } catch (AiException $e) {
            return back()->with('error', 'Nao foi possivel conectar a IA: '.$e->getMessage());
        }

        $setting->update(['last_checked_at' => now()]);

        return back()->with('success', 'Conexao com a IA OK.');
    }

    public function storeLegalDocument(Request $request): RedirectResponse
    {
        if ($request->has('is_active')) {
            $request->merge(['is_active' => $request->boolean('is_active')]);
        }
        if ($request->filled('state')) {
            $request->merge(['state' => strtoupper((string) $request->input('state'))]);
        }

        $data = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'category' => ['required', 'string', Rule::in(array_keys(AiLegalDocument::CATEGORIES))],
            'jurisdiction_level' => ['required', 'string', Rule::in(array_keys(AiLegalDocument::JURISDICTIONS))],
            'state' => [
                Rule::requiredIf(fn () => in_array($request->input('jurisdiction_level'), ['state', 'municipal'], true)),
                'nullable',
                'string',
                'size:2',
            ],
            'city' => [
                Rule::requiredIf(fn () => $request->input('jurisdiction_level') === 'municipal'),
                'nullable',
                'string',
                'max:120',
            ],
            'file' => 'required|file|max:51200|mimes:pdf,txt,md,csv,doc,docx,odt',
            'is_active' => 'sometimes|boolean',
        ]);

        $document = AiLegalDocument::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'],
            'jurisdiction_level' => $data['jurisdiction_level'],
            'state' => in_array($data['jurisdiction_level'], ['state', 'municipal'], true) ? ($data['state'] ?? null) : null,
            'city' => $data['jurisdiction_level'] === 'municipal' ? ($data['city'] ?? null) : null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            'uploaded_by' => Auth::id(),
        ]);

        $file = $request->file('file');
        $disk = config('filesystems.default');
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $path = 'global/ai/legal/'.now()->format('Y/m').'/'.Str::uuid().'.'.$extension;

        try {
            Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));
        } catch (\Throwable $e) {
            $document->forceDelete();

            return back()->withErrors(['file' => 'Nao foi possivel armazenar o arquivo: '.$e->getMessage()])->withInput();
        }

        $document->update([
            'storage_provider' => $disk,
            'storage_bucket' => config("filesystems.disks.{$disk}.bucket"),
            'storage_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size_bytes' => $file->getSize(),
            'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
        ]);

        if ($document->is_active) {
            IndexAiLegalDocument::dispatch($document->id);
        }

        return back()->with('success', "Documento legal \"{$document->title}\" enviado.");
    }

    public function toggleLegalDocument(AiLegalDocument $document): RedirectResponse
    {
        $document->update(['is_active' => ! $document->is_active]);

        if ($document->is_active) {
            IndexAiLegalDocument::dispatch($document->id);
        } else {
            AiLegalDocumentChunk::where('ai_legal_document_id', $document->id)->delete();
        }

        return back()->with('success', 'Status do documento legal atualizado.');
    }

    public function reindexLegalDocument(AiLegalDocument $document): RedirectResponse
    {
        IndexAiLegalDocument::dispatch($document->id);

        return back()->with('success', 'Reindexacao do documento legal enviada para a fila.');
    }

    public function downloadLegalDocument(AiLegalDocument $document): RedirectResponse|StreamedResponse
    {
        abort_unless($document->storage_path, 404);

        $disk = Storage::disk($document->storage_provider);

        try {
            return redirect()->away($disk->temporaryUrl($document->storage_path, now()->addMinutes(10)));
        } catch (\Throwable) {
            return $disk->download($document->storage_path, $document->original_filename);
        }
    }

    public function destroyLegalDocument(AiLegalDocument $document): RedirectResponse
    {
        if ($document->storage_path) {
            Storage::disk($document->storage_provider)->delete($document->storage_path);
        }

        AiLegalDocumentChunk::where('ai_legal_document_id', $document->id)->delete();
        $document->delete();

        return back()->with('success', 'Documento legal removido.');
    }

    private function legalDocumentsPayload(): array
    {
        return AiLegalDocument::query()
            ->with('uploader:id,name')
            ->withCount('chunks')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (AiLegalDocument $document) => [
                'id' => $document->id,
                'title' => $document->title,
                'description' => $document->description,
                'category' => $document->category,
                'category_label' => $document->categoryLabel(),
                'jurisdiction_level' => $document->jurisdiction_level,
                'jurisdiction_label' => $document->jurisdictionLabel(),
                'state' => $document->state,
                'city' => $document->city,
                'is_active' => $document->is_active,
                'original_filename' => $document->original_filename,
                'file_size_bytes' => $document->file_size_bytes,
                'chunks_count' => $document->chunks_count,
                'uploaded_by' => $document->uploader?->name,
                'created_at' => $document->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
