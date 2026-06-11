<?php

namespace App\Http\Controllers\Panel;

use App\Exceptions\StorageQuotaException;
use App\Http\Controllers\Concerns\InteractsWithAttachments;
use App\Http\Controllers\Concerns\ScopesCondominiumsByRole;
use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\Condominium;
use App\Notifications\CommunityPostApproved;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CommunityPostController extends Controller
{
    use InteractsWithAttachments, ScopesCondominiumsByRole;

    public function index(Request $request): Response
    {
        $tenant = app('tenant');
        $condominiumIds = $this->accessibleCondominiums($tenant->id, $request->user())->pluck('id')->all();
        $type = in_array($request->input('post_type'), array_keys(CommunityPost::TYPES), true) ? $request->input('post_type') : null;
        $status = in_array($request->input('status'), array_keys(CommunityPost::STATUSES), true) ? $request->input('status') : null;

        $posts = CommunityPost::with(['condominium:id,name', 'authorUser:id,name', 'authorPerson:id,name'])
            ->whereIn('condominium_id', $condominiumIds)
            ->when($type, fn (Builder $q, string $value) => $q->where('post_type', $value))
            ->when($status, fn (Builder $q, string $value) => $q->where('status', $value))
            ->when($request->input('search'), fn (Builder $q, string $search) => $q->where('title', 'ilike', "%{$search}%"))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 WHEN status = 'published' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (CommunityPost $post) => $this->payload($post));

        return Inertia::render('CommunityBoard/Index', [
            'posts' => $posts,
            'types' => CommunityPost::TYPES,
            'statuses' => CommunityPost::STATUSES,
            'categories' => CommunityPost::CATEGORIES,
            'filters' => [
                'post_type' => $type,
                'status' => $status,
                'search' => $request->input('search'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('CommunityBoard/Create', [
            'condominiums' => $this->condominiumOptions($request),
            'types' => CommunityPost::TYPES,
            'categories' => CommunityPost::CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $publish = (bool) ($data['publish'] ?? true);

        $post = CommunityPost::create([
            'condominium_id' => $data['condominium_id'],
            'author_user_id' => $request->user()?->id,
            'author_person_id' => $request->user()?->person_id,
            'post_type' => $data['post_type'],
            'status' => $publish ? 'published' : 'pending',
            'category' => $data['category'] ?? null,
            'title' => $data['title'],
            'body' => $data['body'],
            'price' => $data['post_type'] === 'classified' ? ($data['price'] ?? null) : null,
            'contact_name' => $data['contact_name'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'published_at' => $publish ? now() : null,
            'expires_at' => $data['expires_at'] ?? null,
            'moderated_by' => $publish ? $request->user()?->id : null,
            'moderated_at' => $publish ? now() : null,
        ]);

        try {
            $this->storeAttachments($request, $post, CommunityPost::ATTACHMENT_ENTITY, 'tenant', $post->condominium_id);
        } catch (StorageQuotaException) {
            // O post e mantido mesmo se a imagem nao puder ser salva.
        }

        return redirect()->route('community-board.index')->with('success', $publish ? 'Publicacao criada.' : 'Publicacao salva como pendente.');
    }

    public function approve(Request $request, CommunityPost $post): RedirectResponse
    {
        $post = $this->authorizePost($post, $request);
        $post->update([
            'status' => 'published',
            'published_at' => $post->published_at ?? now(),
            'moderated_by' => $request->user()?->id,
            'moderated_at' => now(),
            'rejection_reason' => null,
        ]);

        if ($post->authorUser && $post->authorUser->status === 'active') {
            Notification::send($post->authorUser, new CommunityPostApproved($post));
        }

        return back()->with('success', 'Publicacao aprovada.');
    }

    public function reject(Request $request, CommunityPost $post): RedirectResponse
    {
        $post = $this->authorizePost($post, $request);
        $data = $request->validate(['reason' => 'nullable|string|max:180']);

        $post->update([
            'status' => 'rejected',
            'moderated_by' => $request->user()?->id,
            'moderated_at' => now(),
            'rejection_reason' => $data['reason'] ?? null,
        ]);

        return back()->with('success', 'Publicacao rejeitada.');
    }

    public function archive(Request $request, CommunityPost $post): RedirectResponse
    {
        $post = $this->authorizePost($post, $request);
        $post->update(['status' => 'archived']);

        return back()->with('success', 'Publicacao arquivada.');
    }

    public function destroy(Request $request, CommunityPost $post): RedirectResponse
    {
        $post = $this->authorizePost($post, $request);
        $post->delete();

        return back()->with('success', 'Publicacao removida.');
    }

    private function validateData(Request $request): array
    {
        $condominiumIds = $this->accessibleCondominiums(app('tenant')->id, $request->user())->pluck('id')->all();

        return $request->validate(array_merge([
            'condominium_id' => ['required', 'uuid', Rule::in($condominiumIds)],
            'post_type' => ['required', Rule::in(array_keys(CommunityPost::TYPES))],
            'category' => ['nullable', Rule::in(array_keys(CommunityPost::CATEGORIES))],
            'title' => 'required|string|max:180',
            'body' => 'required|string|max:5000',
            'price' => 'nullable|numeric|min:0|max:99999999',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:40',
            'contact_email' => 'nullable|email|max:255',
            'expires_at' => 'nullable|date|after_or_equal:today',
            'publish' => 'boolean',
        ], $this->attachmentRules()));
    }

    private function authorizePost(CommunityPost $post, Request $request): CommunityPost
    {
        abort_unless($post->tenant_id === app('tenant')->id, 403);
        $allowed = $this->accessibleCondominiums($post->tenant_id, $request->user())->pluck('id')->all();
        abort_unless(in_array($post->condominium_id, $allowed, true), 403);

        return $post;
    }

    private function payload(CommunityPost $post): array
    {
        return [
            'id' => $post->id,
            'post_type' => $post->post_type,
            'status' => $post->status,
            'category' => $post->category,
            'title' => $post->title,
            'body' => $post->body,
            'condominium' => $post->condominium?->name,
            'author' => $post->authorPerson?->name ?? $post->authorUser?->name,
            'price' => $post->price !== null ? (float) $post->price : null,
            'contact_name' => $post->contact_name,
            'contact_phone' => $post->contact_phone,
            'contact_email' => $post->contact_email,
            'published_at' => $post->published_at?->toIso8601String(),
            'expires_at' => $post->expires_at?->toDateString(),
            'rejection_reason' => $post->rejection_reason,
            'image' => $post->attachmentsPayload()[0]['id'] ?? null,
        ];
    }

    private function condominiumOptions(Request $request): Collection
    {
        return $this->accessibleCondominiums(app('tenant')->id, $request->user())
            ->map(fn (Condominium $c) => ['value' => $c->id, 'label' => $c->name])
            ->values();
    }
}
