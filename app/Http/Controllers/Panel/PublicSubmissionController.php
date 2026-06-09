<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Concerns\ScopesCondominiumsByRole;
use App\Http\Controllers\Controller;
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
 * Fila de moderação dos envios públicos (auto-cadastro de morador / ocorrência). Aprova ou
 * reprova respeitando o escopo de condomínios por papel. A criação efetiva (Pessoa/Ocorrência)
 * fica no PublicSubmissionService.
 */
class PublicSubmissionController extends Controller
{
    use ScopesCondominiumsByRole;

    public function __construct(private readonly PublicSubmissionService $service) {}

    public function index(Request $request): Response
    {
        $tenant = app('tenant');
        $condominiums = $this->accessibleCondominiums($tenant->id, $request->user());
        $condominiumIds = $condominiums->pluck('id');

        $status = in_array($request->status, array_keys(PublicSubmission::STATUSES), true) ? $request->status : 'pending';

        $submissions = PublicSubmission::where('tenant_id', $tenant->id)
            ->whereIn('condominium_id', $condominiumIds)
            ->with('condominium:id,name')
            ->where('status', $status)
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->condominium_id, fn ($q, $id) => $q->where('condominium_id', $id))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $pendingCount = PublicSubmission::where('tenant_id', $tenant->id)
            ->whereIn('condominium_id', $condominiumIds)
            ->where('status', 'pending')
            ->count();

        return Inertia::render('PublicLinks/Moderation/Index', [
            'submissions' => $submissions,
            'condominiums' => $condominiums->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->values(),
            'types' => PublicSubmission::TYPES,
            'statuses' => PublicSubmission::STATUSES,
            'filters' => [
                'status' => $status,
                'type' => $request->type,
                'condominium_id' => $request->condominium_id,
            ],
            'pendingCount' => $pendingCount,
        ]);
    }

    public function show(Request $request, PublicSubmission $submission): Response
    {
        $this->authorizeSubmission($submission, $request);

        $submission->load(['condominium:id,name', 'reviewer:id,name', 'person:id,name', 'occurrence:id,title', 'attachments']);

        $units = [];
        if ($submission->type === 'resident_signup') {
            $units = Unit::where('condominium_id', $submission->condominium_id)
                ->with('block:id,name')
                ->orderBy('number')
                ->get(['id', 'number', 'block_id'])
                ->map(fn ($u) => ['value' => $u->id, 'label' => $u->block ? "{$u->block->name} — {$u->number}" : $u->number]);
        }

        return Inertia::render('PublicLinks/Moderation/Show', [
            'submission' => [
                'id' => $submission->id,
                'type' => $submission->type,
                'type_label' => PublicSubmission::TYPES[$submission->type] ?? $submission->type,
                'protocol' => $submission->protocol,
                'status' => $submission->status,
                'status_label' => PublicSubmission::STATUSES[$submission->status] ?? $submission->status,
                'name' => $submission->name,
                'email' => $submission->email,
                'phone' => $submission->phone,
                'document' => $submission->document,
                'payload' => $submission->payload,
                'condominium' => $submission->condominium?->only(['id', 'name']),
                'reviewer' => $submission->reviewer?->only(['id', 'name']),
                'reviewed_at' => $submission->reviewed_at,
                'review_notes' => $submission->review_notes,
                'person' => $submission->person?->only(['id', 'name']),
                'occurrence' => $submission->occurrence?->only(['id', 'title']),
                'created_at' => $submission->created_at,
            ],
            'attachments' => $submission->attachmentsPayload(),
            'units' => $units,
            'relations' => PersonUnitLink::typeLabels(),
            'categories' => Occurrence::CATEGORIES,
            'priorities' => Occurrence::PRIORITIES,
            'canManage' => $request->user()->hasPermission('public_links:manage'),
        ]);
    }

    public function approve(Request $request, PublicSubmission $submission): RedirectResponse
    {
        $this->authorizeSubmission($submission, $request, manage: true);

        if ($submission->type === 'resident_signup') {
            $data = $request->validate([
                'unit_id' => 'nullable|uuid',
                'relation' => 'nullable|in:'.implode(',', array_keys(PersonUnitLink::typeLabels())),
                'invite' => 'boolean',
                'channels' => 'array',
                'channels.*' => 'in:email,whatsapp',
            ]);

            // Permite ao gestor ajustar unidade/relação antes de aprovar.
            $payload = $submission->payload ?? [];
            if (! empty($data['unit_id'])) {
                $payload['unit_id'] = $data['unit_id'];
            }
            if (! empty($data['relation'])) {
                $payload['relation'] = $data['relation'];
            }
            $submission->payload = $payload;
            $submission->save();

            $person = $this->service->approveResidentSignup($submission, [
                'invite' => $data['invite'] ?? false,
                'channels' => $data['channels'] ?? ['email'],
            ], $request->user());

            $message = "Morador \"{$person->name}\" cadastrado e vinculado.";
            if (($data['invite'] ?? false) && $person->email) {
                $message .= ' Convite ao portal enviado.';
            }

            return redirect()->route('public-links.moderation.index')->with('success', $message);
        }

        $data = $request->validate([
            'priority' => 'nullable|in:'.implode(',', array_keys(Occurrence::PRIORITIES)),
        ]);

        $occurrence = $this->service->approveOccurrence($submission, [
            'priority' => $data['priority'] ?? 'normal',
        ], $request->user());

        return redirect()->route('occurrences.show', $occurrence)->with('success', 'Ocorrência criada a partir do envio público.');
    }

    public function reject(Request $request, PublicSubmission $submission): RedirectResponse
    {
        $this->authorizeSubmission($submission, $request, manage: true);

        $data = $request->validate([
            'review_notes' => 'nullable|string|max:1000',
        ]);

        $this->service->reject($submission, $data['review_notes'] ?? null, $request->user());

        return redirect()->route('public-links.moderation.index')->with('success', 'Envio reprovado.');
    }

    private function authorizeSubmission(PublicSubmission $submission, Request $request, bool $manage = false): void
    {
        abort_unless($submission->tenant_id === app('tenant')->id, 403);

        if ($manage) {
            abort_unless($request->user()->hasPermission('public_links:manage'), 403);
        }

        $allowedIds = $this->accessibleCondominiums($submission->tenant_id, $request->user())->pluck('id')->all();
        abort_unless(in_array($submission->condominium_id, $allowedIds, true), 403);
    }
}
