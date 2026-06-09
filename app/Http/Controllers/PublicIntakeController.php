<?php

namespace App\Http\Controllers;

use App\Models\CondominiumPublicLink;
use App\Models\Occurrence;
use App\Models\PersonUnitLink;
use App\Models\PublicSubmission;
use App\Models\Unit;
use App\Services\PublicSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Intake público (sem autenticação) dos links/QR por condomínio: auto-cadastro de morador e
 * abertura de ocorrência. O tenant já vem resolvido pelo domínio (ResolveTenant), e o token é
 * escopado ao tenant pelo global scope de CondominiumPublicLink. Todo envio entra como
 * PublicSubmission "pending" e só vira Pessoa/Ocorrência após moderação no painel.
 */
class PublicIntakeController extends Controller
{
    public function __construct(private readonly PublicSubmissionService $service) {}

    public function landing(string $token): Response
    {
        $link = $this->resolveLink($token);

        return Inertia::render('Public/Landing', [
            'token' => $link->token,
            'condominium' => ['name' => $link->condominium->name],
            'allow' => [
                'resident_signup' => $link->allow_resident_signup,
                'occurrence' => $link->allow_occurrence,
            ],
        ]);
    }

    public function residentForm(string $token): Response
    {
        $link = $this->resolveLink($token, requireAction: 'allow_resident_signup');

        return Inertia::render('Public/ResidentSignup', [
            'token' => $link->token,
            'condominium' => ['name' => $link->condominium->name],
            'units' => $this->unitOptions($link),
            'relations' => PersonUnitLink::typeLabels(),
        ]);
    }

    public function residentStore(Request $request, string $token): RedirectResponse
    {
        $link = $this->resolveLink($token, requireAction: 'allow_resident_signup');

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'document' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
            'phone' => 'required|string|max:30',
            'person_type' => 'nullable|in:individual,company',
            'relation' => 'required|in:'.implode(',', array_keys(PersonUnitLink::typeLabels())),
            'unit_id' => "required|uuid|exists:units,id,condominium_id,{$link->condominium_id}",
            'notes' => 'nullable|string|max:1000',
        ]);

        $unit = Unit::where('id', $data['unit_id'])->first();

        $submission = PublicSubmission::create([
            'tenant_id' => $link->tenant_id,
            'condominium_id' => $link->condominium_id,
            'type' => 'resident_signup',
            'status' => 'pending',
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'],
            'document' => $data['document'] ?? null,
            'payload' => [
                'relation' => $data['relation'],
                'unit_id' => $data['unit_id'],
                'unit_label' => $unit?->number,
                'person_type' => $data['person_type'] ?? 'individual',
                'notes' => $data['notes'] ?? null,
            ],
            'ip_address' => $request->ip(),
        ]);

        $this->service->notifyManagers($submission);

        return redirect()->route('public.intake.sent', ['token' => $link->token]);
    }

    public function occurrenceForm(string $token): Response
    {
        $link = $this->resolveLink($token, requireAction: 'allow_occurrence');

        return Inertia::render('Public/Occurrence', [
            'token' => $link->token,
            'condominium' => ['name' => $link->condominium->name],
            'units' => $this->unitOptions($link),
            'categories' => \App\Models\Category::optionsFor($link->tenant_id, 'occurrence', Occurrence::CATEGORIES),
        ]);
    }

    public function occurrenceStore(Request $request, string $token): RedirectResponse
    {
        $link = $this->resolveLink($token, requireAction: 'allow_occurrence');

        $categories = \App\Models\Category::optionsFor($link->tenant_id, 'occurrence', Occurrence::CATEGORIES);

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'nullable|email|max:150',
            'phone' => 'required|string|max:30',
            'unit_id' => "nullable|uuid|exists:units,id,condominium_id,{$link->condominium_id}",
            'title' => 'required|string|max:200',
            'description' => 'required|string|max:5000',
            'category' => 'required|in:'.implode(',', array_keys($categories)),
        ]);

        $unit = ! empty($data['unit_id']) ? Unit::where('id', $data['unit_id'])->first() : null;

        $submission = PublicSubmission::create([
            'tenant_id' => $link->tenant_id,
            'condominium_id' => $link->condominium_id,
            'type' => 'occurrence',
            'status' => 'pending',
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'],
            'payload' => [
                'title' => $data['title'],
                'description' => $data['description'],
                'category' => $data['category'],
                'unit_id' => $data['unit_id'] ?? null,
                'unit_label' => $unit?->number,
            ],
            'ip_address' => $request->ip(),
        ]);

        $this->service->notifyManagers($submission);

        return redirect()->route('public.intake.sent', ['token' => $link->token]);
    }

    public function sent(string $token): Response
    {
        $link = $this->resolveLink($token);

        return Inertia::render('Public/Sent', [
            'token' => $link->token,
            'condominium' => ['name' => $link->condominium->name],
        ]);
    }

    /** Resolve o link ativo pelo token (escopado ao tenant atual) e valida a ação habilitada. */
    private function resolveLink(string $token, ?string $requireAction = null): CondominiumPublicLink
    {
        $link = CondominiumPublicLink::with('condominium:id,name')
            ->where('token', $token)
            ->where('active', true)
            ->first();

        abort_if(! $link || ! $link->condominium, 404);

        if ($requireAction && ! $link->{$requireAction}) {
            abort(404);
        }

        return $link;
    }

    /** Opções de unidade (número/bloco) para o formulário público. */
    private function unitOptions(CondominiumPublicLink $link): \Illuminate\Support\Collection
    {
        return Unit::where('condominium_id', $link->condominium_id)
            ->with('block:id,name')
            ->orderBy('number')
            ->get(['id', 'number', 'block_id'])
            ->map(fn ($u) => [
                'value' => $u->id,
                'label' => $u->block ? "{$u->block->name} — {$u->number}" : $u->number,
            ]);
    }
}
