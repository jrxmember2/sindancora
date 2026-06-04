<?php

namespace App\Http\Controllers\Panel;

use App\Exceptions\StorageQuotaException;
use App\Http\Controllers\Concerns\InteractsWithAttachments;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Condominium;
use App\Services\AnnouncementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    use InteractsWithAttachments;

    public function __construct(private readonly AnnouncementService $service)
    {
    }

    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $announcements = Announcement::where('tenant_id', $tenant->id)
            ->select(['id', 'condominium_id', 'title', 'category', 'urgency', 'status', 'published_at', 'publish_at', 'expires_at', 'created_at'])
            ->with('condominium:id,name')
            ->when($request->search, fn ($q, $s) => $q->where('title', 'ilike', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Announcements/Index', [
            'announcements' => $announcements,
            'condominiums' => $this->condominiumOptions($tenant->id),
            'categories' => Announcement::CATEGORIES,
            'urgencies' => Announcement::URGENCIES,
            'filters' => $request->only(['search', 'status', 'condominium_id']),
        ]);
    }

    public function create(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Announcements/Create', [
            'condominiums' => $this->condominiumOptions($tenant->id),
            'categories' => Announcement::CATEGORIES,
            'urgencies' => Announcement::URGENCIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $this->validated($request, $tenant->id);
        $action = $request->input('action', 'draft');

        $announcement = Announcement::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'created_by' => Auth::id(),
            'status' => 'draft',
        ]));

        if ($error = $this->uploadAttachments($request, $announcement)) {
            return $error;
        }

        return $this->applyAction($announcement, $action, 'criado');
    }

    public function show(Announcement $announcement): Response
    {
        $announcement = $this->authorizeTenant($announcement);
        $announcement->load('condominium:id,name', 'creator:id,name', 'attachments');

        return Inertia::render('Announcements/Show', [
            'announcement' => $announcement,
            'attachments' => $announcement->attachmentsPayload(),
            'categories' => Announcement::CATEGORIES,
            'urgencies' => Announcement::URGENCIES,
        ]);
    }

    public function edit(Announcement $announcement): Response
    {
        $announcement = $this->authorizeTenant($announcement);
        $tenant = app('tenant');

        return Inertia::render('Announcements/Edit', [
            'announcement' => $announcement,
            'attachments' => $announcement->attachmentsPayload(),
            'condominiums' => $this->condominiumOptions($tenant->id),
            'categories' => Announcement::CATEGORIES,
            'urgencies' => Announcement::URGENCIES,
        ]);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $announcement = $this->authorizeTenant($announcement);
        $data = $this->validated($request, $announcement->tenant_id);
        $action = $request->input('action', 'draft');

        $announcement->update($data);

        if ($error = $this->uploadAttachments($request, $announcement)) {
            return $error;
        }

        return $this->applyAction($announcement, $action, 'atualizado');
    }

    /** Valida e sobe os anexos do comunicado (visíveis aos moradores). Retorna erro em estouro de cota. */
    private function uploadAttachments(Request $request, Announcement $announcement): ?RedirectResponse
    {
        $request->validate($this->attachmentRules());

        try {
            $this->storeAttachments($request, $announcement, Announcement::ATTACHMENT_ENTITY, 'public_to_residents', $announcement->condominium_id);
        } catch (StorageQuotaException $e) {
            return back()->withErrors(['attachments' => $e->getMessage()])->withInput();
        }

        return null;
    }

    public function publish(Announcement $announcement): RedirectResponse
    {
        $announcement = $this->authorizeTenant($announcement);

        return $this->applyAction($announcement, 'publish', 'atualizado');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement = $this->authorizeTenant($announcement);
        $announcement->delete();

        return redirect()->route('announcements.index')->with('success', 'Comunicado removido.');
    }

    /**
     * Aplica a ação de submissão (rascunho, publicar agora ou agendar) e redireciona.
     */
    private function applyAction(Announcement $announcement, string $action, string $verb): RedirectResponse
    {
        if ($action !== 'publish') {
            return redirect()->route('announcements.show', $announcement)
                ->with('success', "Comunicado {$verb} como rascunho.");
        }

        // Agendamento: data futura → publica depois, via comando agendado.
        if ($announcement->publish_at && $announcement->publish_at->isFuture()) {
            return redirect()->route('announcements.show', $announcement)
                ->with('success', 'Comunicado agendado para '.$announcement->publish_at->format('d/m/Y H:i').'.');
        }

        $this->service->publish($announcement);

        return redirect()->route('announcements.show', $announcement)
            ->with('success', 'Comunicado publicado. E-mails enfileirados para os moradores.');
    }

    private function validated(Request $request, string $tenantId): array
    {
        return $request->validate([
            'condominium_id' => "required|uuid|exists:condominiums,id,tenant_id,{$tenantId}",
            'title' => 'required|string|max:200',
            'body' => 'required|string',
            'category' => 'required|in:'.implode(',', array_keys(Announcement::CATEGORIES)),
            'urgency' => 'required|in:'.implode(',', array_keys(Announcement::URGENCIES)),
            'publish_at' => 'nullable|date',
            'expires_at' => $request->filled('publish_at')
                ? 'nullable|date|after:publish_at'
                : 'nullable|date',
        ]);
    }

    private function authorizeTenant(Announcement $announcement): Announcement
    {
        abort_unless($announcement->tenant_id === app('tenant')->id, 403);

        return $announcement;
    }

    private function condominiumOptions(string $tenantId): \Illuminate\Support\Collection
    {
        return Condominium::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]);
    }
}
