<?php

namespace App\Http\Controllers\Panel;

use App\Exceptions\StorageQuotaException;
use App\Http\Controllers\Concerns\InteractsWithAttachments;
use App\Http\Controllers\Concerns\ScopesCondominiumsByRole;
use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Models\LostFoundItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LostFoundController extends Controller
{
    use InteractsWithAttachments, ScopesCondominiumsByRole;

    public function index(Request $request): Response
    {
        $tenant = app('tenant');
        $condominiumIds = $this->accessibleCondominiums($tenant->id, $request->user())->pluck('id')->all();
        $status = in_array($request->input('status'), array_keys(LostFoundItem::STATUSES), true) ? $request->input('status') : null;
        $type = in_array($request->input('type'), array_keys(LostFoundItem::TYPES), true) ? $request->input('type') : null;

        $items = LostFoundItem::with('condominium:id,name')
            ->whereIn('condominium_id', $condominiumIds)
            ->when($status, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($type, fn (Builder $q, string $t) => $q->where('type', $t))
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (LostFoundItem $i) => $this->payload($i));

        return Inertia::render('LostFound/Index', [
            'items' => $items,
            'condominiums' => $this->condominiumOptions($request),
            'types' => LostFoundItem::TYPES,
            'statuses' => LostFoundItem::STATUSES,
            'filters' => ['status' => $status, 'type' => $type],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('LostFound/Create', [
            'condominiums' => $this->condominiumOptions($request),
            'types' => LostFoundItem::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $condominiumIds = $this->accessibleCondominiums(app('tenant')->id, $request->user())->pluck('id')->all();

        $data = $request->validate([
            'condominium_id' => ['required', 'uuid', Rule::in($condominiumIds)],
            'type' => ['required', Rule::in(array_keys(LostFoundItem::TYPES))],
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'category' => 'nullable|string|max:100',
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

        return redirect()->route('lost-found.index')->with('success', 'Item registrado.');
    }

    public function resolve(Request $request, LostFoundItem $item): RedirectResponse
    {
        $this->authorizeItem($item, $request);
        $item->update(['status' => 'resolved', 'resolved_at' => now()]);

        return back()->with('success', 'Item marcado como resolvido.');
    }

    public function destroy(Request $request, LostFoundItem $item): RedirectResponse
    {
        $this->authorizeItem($item, $request);
        $item->delete();

        return back()->with('success', 'Item removido.');
    }

    private function authorizeItem(LostFoundItem $item, Request $request): void
    {
        abort_unless($item->tenant_id === app('tenant')->id, 403);
        $allowed = $this->accessibleCondominiums($item->tenant_id, $request->user())->pluck('id')->all();
        abort_unless(in_array($item->condominium_id, $allowed, true), 403);
    }

    private function payload(LostFoundItem $i): array
    {
        return [
            'id' => $i->id,
            'type' => $i->type,
            'title' => $i->title,
            'description' => $i->description,
            'category' => $i->category,
            'location' => $i->location,
            'status' => $i->status,
            'condominium' => $i->condominium?->name,
            'occurred_on' => $i->occurred_on?->toDateString(),
            'photo' => $i->attachmentsPayload()[0]['id'] ?? null,
        ];
    }

    private function condominiumOptions(Request $request): Collection
    {
        return $this->accessibleCondominiums(app('tenant')->id, $request->user())
            ->map(fn (Condominium $c) => ['value' => $c->id, 'label' => $c->name])
            ->values();
    }
}
