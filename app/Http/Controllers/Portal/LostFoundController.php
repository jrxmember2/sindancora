<?php

namespace App\Http\Controllers\Portal;

use App\Exceptions\StorageQuotaException;
use App\Http\Controllers\Concerns\InteractsWithAttachments;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\InteractsWithResident;
use App\Models\LostFoundItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Achados & perdidos no portal: o morador vê os itens dos seus condomínios e pode reportar um item.
 */
class LostFoundController extends Controller
{
    use InteractsWithAttachments, InteractsWithResident;

    public function index(): Response
    {
        $items = LostFoundItem::with('condominium:id,name')
            ->whereIn('condominium_id', $this->condominiumIds())
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (LostFoundItem $i) => [
                'id' => $i->id,
                'type' => $i->type,
                'title' => $i->title,
                'description' => $i->description,
                'location' => $i->location,
                'status' => $i->status,
                'condominium' => $i->condominium?->name,
                'occurred_on' => $i->occurred_on?->toDateString(),
                'photo' => $i->attachmentsPayload()[0]['id'] ?? null,
            ]);

        return Inertia::render('Portal/LostFound/Index', [
            'items' => $items,
            'types' => LostFoundItem::TYPES,
            'statuses' => LostFoundItem::STATUSES,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Portal/LostFound/Create', [
            'condominiums' => $this->activeLinks()
                ->map(fn ($l) => ['value' => $l->unit->condominium_id, 'label' => $l->unit->condominium?->name])
                ->unique('value')->values(),
            'types' => LostFoundItem::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'condominium_id' => ['required', 'uuid', Rule::in($this->condominiumIds())],
            'type' => ['required', Rule::in(array_keys(LostFoundItem::TYPES))],
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'location' => 'nullable|string|max:150',
            'occurred_on' => 'nullable|date',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $item = LostFoundItem::create(array_merge($data, [
            'status' => 'open',
            'reported_by' => $request->user()?->id,
        ]));

        try {
            $this->storeAttachments($request, $item, LostFoundItem::ATTACHMENT_ENTITY, 'tenant', $item->condominium_id, 'photo');
        } catch (StorageQuotaException) {
            // ignora
        }

        return redirect()->route('portal.lost-found.index')->with('success', 'Item registrado. A administração vai acompanhar.');
    }
}
