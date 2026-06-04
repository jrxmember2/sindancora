<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\InteractsWithResident;
use App\Models\Announcement;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    use InteractsWithResident;

    public function index(): Response
    {
        $userId = Auth::id();
        $condominiumIds = $this->condominiumIds() ?: ['-'];

        $announcements = Announcement::query()
            ->whereIn('condominium_id', $condominiumIds)
            ->visible()
            ->with('condominium:id,name')
            ->withExists(['reads as is_read' => fn ($q) => $q->where('user_id', $userId)])
            ->orderByDesc('published_at')
            ->paginate(15);

        return Inertia::render('Portal/Announcements/Index', [
            'announcements' => $announcements,
            'categories' => Announcement::CATEGORIES,
            'urgencies' => Announcement::URGENCIES,
        ]);
    }

    public function show(Announcement $announcement): Response
    {
        $this->authorizeVisible($announcement);

        // Confirmação de leitura (idempotente).
        $announcement->reads()->firstOrCreate(
            ['user_id' => Auth::id()],
            ['tenant_id' => $announcement->tenant_id, 'read_at' => now()],
        );

        $announcement->load('condominium:id,name', 'creator:id,name', 'attachments');

        return Inertia::render('Portal/Announcements/Show', [
            'announcement' => $announcement,
            'attachments' => $announcement->attachmentsPayload(),
            'categories' => Announcement::CATEGORIES,
            'urgencies' => Announcement::URGENCIES,
        ]);
    }

    /** Garante que o comunicado está visível e pertence a um condomínio do morador. */
    private function authorizeVisible(Announcement $announcement): void
    {
        abort_unless($announcement->tenant_id === app('tenant')->id, 403);
        abort_unless(in_array($announcement->condominium_id, $this->condominiumIds(), true), 403);
        abort_unless($announcement->status === 'published' && $announcement->published_at && ! $announcement->isExpired(), 404);
    }
}
