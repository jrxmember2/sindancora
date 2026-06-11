<?php

namespace App\Http\Controllers\Portal;

use App\Exceptions\StorageQuotaException;
use App\Http\Controllers\Concerns\InteractsWithAttachments;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\InteractsWithResident;
use App\Models\CommunityPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CommunityPostController extends Controller
{
    use InteractsWithAttachments, InteractsWithResident;

    public function index(): Response
    {
        $posts = CommunityPost::with(['condominium:id,name', 'authorPerson:id,name', 'authorUser:id,name'])
            ->whereIn('condominium_id', $this->condominiumIds())
            ->published()
            ->orderByDesc('published_at')
            ->limit(100)
            ->get()
            ->map(fn (CommunityPost $post) => $this->payload($post));

        return Inertia::render('Portal/CommunityBoard/Index', [
            'posts' => $posts,
            'types' => CommunityPost::TYPES,
            'categories' => CommunityPost::CATEGORIES,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Portal/CommunityBoard/Create', [
            'condominiums' => $this->activeLinks()
                ->map(fn ($link) => ['value' => $link->unit->condominium_id, 'label' => $link->unit->condominium?->name])
                ->unique('value')
                ->values(),
            'categories' => CommunityPost::CATEGORIES,
            'defaults' => [
                'contact_name' => $this->resident()->name,
                'contact_phone' => $this->resident()->phone,
                'contact_email' => $this->resident()->email,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(array_merge([
            'condominium_id' => ['required', 'uuid', Rule::in($this->condominiumIds())],
            'category' => ['nullable', Rule::in(array_keys(CommunityPost::CATEGORIES))],
            'title' => 'required|string|max:180',
            'body' => 'required|string|max:3000',
            'price' => 'nullable|numeric|min:0|max:99999999',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:40',
            'contact_email' => 'nullable|email|max:255',
            'expires_at' => 'nullable|date|after_or_equal:today',
        ], $this->attachmentRules()));

        $post = CommunityPost::create([
            'condominium_id' => $data['condominium_id'],
            'author_user_id' => $request->user()?->id,
            'author_person_id' => $this->resident()->id,
            'post_type' => 'classified',
            'status' => 'pending',
            'category' => $data['category'] ?? null,
            'title' => $data['title'],
            'body' => $data['body'],
            'price' => $data['price'] ?? null,
            'contact_name' => $data['contact_name'] ?? $this->resident()->name,
            'contact_phone' => $data['contact_phone'] ?? $this->resident()->phone,
            'contact_email' => $data['contact_email'] ?? $this->resident()->email,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        try {
            $this->storeAttachments($request, $post, CommunityPost::ATTACHMENT_ENTITY, 'tenant', $post->condominium_id);
        } catch (StorageQuotaException) {
            // A moderacao ainda recebe o texto do classificado.
        }

        return redirect()->route('portal.community-board.index')->with('success', 'Classificado enviado para moderacao.');
    }

    private function payload(CommunityPost $post): array
    {
        return [
            'id' => $post->id,
            'post_type' => $post->post_type,
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
            'image' => $post->attachmentsPayload()[0]['id'] ?? null,
        ];
    }
}
