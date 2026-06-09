<?php

namespace App\Http\Controllers;

use App\Exceptions\StorageQuotaException;
use App\Http\Controllers\Concerns\InteractsWithAttachments;
use App\Models\CondominiumPublicLink;
use App\Models\Occurrence;
use App\Models\PersonUnitLink;
use App\Models\PublicSubmission;
use App\Models\Unit;
use App\Services\CaptchaVerifier;
use App\Services\PublicSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Intake público (sem autenticação) dos links/QR por condomínio: auto-cadastro de morador e
 * abertura de ocorrência. O tenant já vem resolvido pelo domínio (ResolveTenant), e o token é
 * escopado ao tenant pelo global scope de CondominiumPublicLink. Todo envio entra como
 * PublicSubmission "pending" e só vira Pessoa/Ocorrência após moderação no painel.
 *
 * Anti-abuso: honeypot, captcha opcional (Turnstile), throttle por IP (rotas) e limite por
 * condomínio + dedupe (aqui).
 */
class PublicIntakeController extends Controller
{
    use InteractsWithAttachments;

    // Máximo de envios pendentes por IP+condomínio em 24h.
    private const MAX_PENDING_PER_IP_DAY = 5;

    public function __construct(
        private readonly PublicSubmissionService $service,
        private readonly CaptchaVerifier $captcha,
    ) {}

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
            'captchaSiteKey' => $this->captcha->siteKey(),
        ]);
    }

    public function residentStore(Request $request, string $token): RedirectResponse
    {
        $link = $this->resolveLink($token, requireAction: 'allow_resident_signup');

        // Honeypot: bot preencheu o campo oculto → finge sucesso e descarta.
        if (filled($request->input('company_site'))) {
            return $this->sentRedirect($link);
        }

        $this->verifyCaptcha($request);

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

        if ($redirect = $this->guardAbuse($request, $link, 'resident_signup', $data['phone'])) {
            return $redirect;
        }

        $unit = Unit::where('id', $data['unit_id'])->first();

        $submission = PublicSubmission::create([
            'tenant_id' => $link->tenant_id,
            'condominium_id' => $link->condominium_id,
            'type' => 'resident_signup',
            'status' => 'pending',
            'protocol' => PublicSubmission::generateProtocol($link->tenant_id),
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

        return $this->sentRedirect($link, $submission->protocol);
    }

    public function occurrenceForm(string $token): Response
    {
        $link = $this->resolveLink($token, requireAction: 'allow_occurrence');

        return Inertia::render('Public/Occurrence', [
            'token' => $link->token,
            'condominium' => ['name' => $link->condominium->name],
            'units' => $this->unitOptions($link),
            'categories' => \App\Models\Category::optionsFor($link->tenant_id, 'occurrence', Occurrence::CATEGORIES),
            'captchaSiteKey' => $this->captcha->siteKey(),
        ]);
    }

    public function occurrenceStore(Request $request, string $token): RedirectResponse
    {
        $link = $this->resolveLink($token, requireAction: 'allow_occurrence');

        if (filled($request->input('company_site'))) {
            return $this->sentRedirect($link);
        }

        $this->verifyCaptcha($request);

        $categories = \App\Models\Category::optionsFor($link->tenant_id, 'occurrence', Occurrence::CATEGORIES);

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'nullable|email|max:150',
            'phone' => 'required|string|max:30',
            'unit_id' => "nullable|uuid|exists:units,id,condominium_id,{$link->condominium_id}",
            'title' => 'required|string|max:200',
            'description' => 'required|string|max:5000',
            'category' => 'required|in:'.implode(',', array_keys($categories)),
            'photos' => 'nullable|array|max:3',
            'photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        if ($redirect = $this->guardAbuse($request, $link, 'occurrence', $data['phone'])) {
            return $redirect;
        }

        $unit = ! empty($data['unit_id']) ? Unit::where('id', $data['unit_id'])->first() : null;

        $submission = PublicSubmission::create([
            'tenant_id' => $link->tenant_id,
            'condominium_id' => $link->condominium_id,
            'type' => 'occurrence',
            'status' => 'pending',
            'protocol' => PublicSubmission::generateProtocol($link->tenant_id),
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

        // Fotos opcionais: anexadas à submission; re-apontadas para a ocorrência na aprovação.
        // Estouro de cota não derruba o envio — a ocorrência segue sem as fotos.
        try {
            $this->storeAttachments($request, $submission, PublicSubmission::ATTACHMENT_ENTITY, 'tenant', $link->condominium_id, 'photos');
        } catch (StorageQuotaException) {
            // ignora: envio já persistido
        }

        $this->service->notifyManagers($submission);

        return $this->sentRedirect($link, $submission->protocol);
    }

    public function sent(Request $request, string $token): Response
    {
        $link = $this->resolveLink($token);

        return Inertia::render('Public/Sent', [
            'token' => $link->token,
            'condominium' => ['name' => $link->condominium->name],
            'protocol' => $request->session()->get('intake_protocol'),
        ]);
    }

    public function statusForm(string $token): Response
    {
        $link = $this->resolveLink($token);

        return Inertia::render('Public/Status', [
            'token' => $link->token,
            'condominium' => ['name' => $link->condominium->name],
            'result' => null,
        ]);
    }

    public function statusCheck(Request $request, string $token): Response
    {
        $link = $this->resolveLink($token);

        $data = $request->validate([
            'protocol' => 'required|string|max:12',
            'phone' => 'required|string|max:30',
        ]);

        $phoneDigits = preg_replace('/\D/', '', $data['phone']);

        $submission = PublicSubmission::where('tenant_id', $link->tenant_id)
            ->where('condominium_id', $link->condominium_id)
            ->whereRaw('UPPER(protocol) = ?', [strtoupper(trim($data['protocol']))])
            ->get()
            ->first(fn (PublicSubmission $s) => preg_replace('/\D/', '', (string) $s->phone) === $phoneDigits);

        $result = $submission ? [
            'found' => true,
            'type_label' => PublicSubmission::TYPES[$submission->type] ?? $submission->type,
            'status' => $submission->status,
            'status_label' => PublicSubmission::STATUSES[$submission->status] ?? $submission->status,
            'created_at' => $submission->created_at?->toIso8601String(),
        ] : ['found' => false];

        return Inertia::render('Public/Status', [
            'token' => $link->token,
            'condominium' => ['name' => $link->condominium->name],
            'result' => $result,
            'submitted' => ['protocol' => $data['protocol']],
        ]);
    }

    /** Verifica o captcha (Turnstile); no-op quando não configurado. */
    private function verifyCaptcha(Request $request): void
    {
        if (! $this->captcha->verify($request->input('cf-turnstile-response'), $request->ip())) {
            throw ValidationException::withMessages([
                'captcha' => 'Confirme que você não é um robô e tente novamente.',
            ]);
        }
    }

    /**
     * Bloqueios anti-abuso pós-validação: dedupe de envio idêntico recente e teto por IP/condomínio.
     * Retorna um redirect para encerrar o fluxo, ou null para prosseguir.
     */
    private function guardAbuse(Request $request, CondominiumPublicLink $link, string $type, string $phone): ?RedirectResponse
    {
        $phoneDigits = preg_replace('/\D/', '', $phone);

        // Dedupe: mesmo tipo + telefone + condomínio pendente nos últimos 10 min → não duplica.
        $duplicate = PublicSubmission::where('tenant_id', $link->tenant_id)
            ->where('condominium_id', $link->condominium_id)
            ->where('type', $type)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(10))
            ->whereRaw("regexp_replace(coalesce(phone,''), '\\D', '', 'g') = ?", [$phoneDigits])
            ->first();

        if ($duplicate) {
            return $this->sentRedirect($link, $duplicate->protocol);
        }

        // Teto por IP+condomínio em 24h.
        $recent = PublicSubmission::where('tenant_id', $link->tenant_id)
            ->where('condominium_id', $link->condominium_id)
            ->where('ip_address', $request->ip())
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($recent >= self::MAX_PENDING_PER_IP_DAY) {
            throw ValidationException::withMessages([
                'phone' => 'Recebemos muitos envios deste dispositivo hoje. Tente novamente mais tarde ou procure a administração.',
            ]);
        }

        return null;
    }

    /** Redireciona à confirmação, opcionalmente carregando o protocolo na sessão. */
    private function sentRedirect(CondominiumPublicLink $link, ?string $protocol = null): RedirectResponse
    {
        return redirect()
            ->route('public.intake.sent', ['token' => $link->token])
            ->with('intake_protocol', $protocol);
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
