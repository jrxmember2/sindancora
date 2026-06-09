<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Occurrence;
use App\Models\PersonUnitLink;
use App\Models\QuotationProposal;
use App\Models\StorageObject;
use App\Models\User;
use App\Models\Work;
use App\Services\StorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download e remoção de anexos genéricos (StorageObject) usados por Comunicados,
 * Ocorrências e Áreas comuns. O controle de acesso é resolvido pelo entity_type,
 * reusando as regras de cada módulo (inclui o morador no próprio chamado/comunicado).
 */
class AttachmentController extends Controller
{
    public function __construct(private readonly StorageService $storage) {}

    public function download(Request $request, StorageObject $object): RedirectResponse|StreamedResponse
    {
        $this->assertSameTenant($object);
        abort_if($object->deleted_at !== null, 404);
        abort_unless($this->canView($request->user(), $object), 403);

        $disk = Storage::disk($object->storage_provider);

        try {
            return redirect()->away($disk->temporaryUrl($object->storage_path, now()->addMinutes(10)));
        } catch (\Throwable) {
            return $disk->download($object->storage_path, $object->original_filename);
        }
    }

    public function destroy(Request $request, StorageObject $object): RedirectResponse
    {
        $this->assertSameTenant($object);
        abort_unless($this->canManage($request->user(), $object), 403);

        $this->storage->delete($object); // soft delete → lixeira 30 dias

        return back()->with('success', 'Anexo removido.');
    }

    private function assertSameTenant(StorageObject $object): void
    {
        abort_unless($object->tenant_id === app('tenant')->id, 403);
    }

    private function canView(?User $user, StorageObject $object): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }

        return match ($object->entity_type) {
            'announcement' => $user->hasPermission('announcements:read') || $this->residentSeesAnnouncement($user, $object->entity_id),
            'occurrence' => $user->hasPermission('occurrences:read') || $this->isOccurrenceAuthor($user, $object->entity_id),
            'common_area' => true, // áreas comuns são visíveis aos moradores do tenant
            'document' => $user->hasPermission('documents:read'),
            'quotation_proposal' => $user->hasPermission('quotations:read') && $this->planAllows('quotations'),
            'work' => $user->hasPermission('works:read') && $this->planAllows('works'),
            default => false,
        };
    }

    private function canManage(?User $user, StorageObject $object): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }

        return match ($object->entity_type) {
            'announcement' => $user->hasPermission('announcements:update'),
            'occurrence' => $user->hasPermission('occurrences:update') || $this->isOccurrenceAuthor($user, $object->entity_id),
            'common_area' => $user->hasPermission('reservations:approve'),
            'quotation_proposal' => $this->canManageQuotationProposal($user, $object->entity_id),
            'work' => $this->canManageWork($user, $object->entity_id),
            default => false,
        };
    }

    private function canManageWork(User $user, ?string $workId): bool
    {
        if (! $workId || ! $user->hasPermission('works:update') || ! $this->planAllows('works')) {
            return false;
        }

        return Work::where('tenant_id', app('tenant')->id)->whereKey($workId)->exists();
    }

    private function canManageQuotationProposal(User $user, ?string $proposalId): bool
    {
        if (! $proposalId || ! $user->hasPermission('quotations:update') || ! $this->planAllows('quotations')) {
            return false;
        }

        $proposal = QuotationProposal::with('quotation:id,status')
            ->where('tenant_id', app('tenant')->id)
            ->find($proposalId);

        return $proposal !== null
            && $proposal->status !== 'approved'
            && $proposal->quotation?->status !== 'approved';
    }

    private function planAllows(string $module): bool
    {
        return (bool) app('tenant')->activePlan()?->hasModule($module);
    }

    private function residentSeesAnnouncement(User $user, ?string $announcementId): bool
    {
        if (! $user->person_id || ! $announcementId) {
            return false;
        }

        $announcement = Announcement::find($announcementId);
        if (! $announcement || $announcement->status !== 'published') {
            return false;
        }

        return $this->residentCondominiumIds($user)->contains($announcement->condominium_id);
    }

    private function isOccurrenceAuthor(User $user, ?string $occurrenceId): bool
    {
        if (! $occurrenceId) {
            return false;
        }

        $occurrence = Occurrence::find($occurrenceId);

        return $occurrence !== null && $occurrence->created_by === $user->id;
    }

    private function residentCondominiumIds(User $user): \Illuminate\Support\Collection
    {
        return PersonUnitLink::where('person_id', $user->person_id)
            ->whereNull('end_date')
            ->join('units', 'units.id', '=', 'person_unit_links.unit_id')
            ->distinct()
            ->pluck('units.condominium_id');
    }
}
