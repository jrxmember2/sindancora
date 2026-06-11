<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Concerns\ScopesCondominiumsByRole;
use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Models\Poll;
use App\Models\User;
use App\Models\PersonUnitLink;
use App\Notifications\PollOpened;
use App\Services\PollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PollController extends Controller
{
    use ScopesCondominiumsByRole;

    public function __construct(private readonly PollService $polls) {}

    public function index(Request $request): Response
    {
        $tenant = app('tenant');
        $condominiumIds = $this->accessibleCondominiums($tenant->id, $request->user())->pluck('id')->all();

        $polls = Poll::with('condominium:id,name')
            ->withCount('votes')
            ->whereIn('condominium_id', $condominiumIds)
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn (Poll $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'status' => $p->status,
                'condominium' => $p->condominium?->name,
                'votes_count' => $p->votes_count,
                'created_at' => $p->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Polls/Index', [
            'polls' => $polls,
            'statuses' => Poll::STATUSES,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Polls/Create', [
            'condominiums' => $this->condominiumOptions($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePoll($request);

        $poll = DB::transaction(function () use ($data, $request) {
            $poll = Poll::create([
                'condominium_id' => $data['condominium_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => 'draft',
                'is_anonymous' => $data['is_anonymous'] ?? false,
                'closes_at' => $data['closes_at'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            foreach ($data['options'] as $i => $label) {
                $poll->options()->create(['tenant_id' => $poll->tenant_id, 'label' => $label, 'sort_order' => $i]);
            }

            return $poll;
        });

        return redirect()->route('polls.show', $poll)->with('success', 'Enquete criada.');
    }

    public function show(Request $request, Poll $poll): Response
    {
        $poll = $this->authorizePoll($poll, $request);
        $poll->load('condominium:id,name');

        return Inertia::render('Polls/Show', [
            'poll' => [
                'id' => $poll->id,
                'title' => $poll->title,
                'description' => $poll->description,
                'status' => $poll->status,
                'is_anonymous' => $poll->is_anonymous,
                'closes_at' => $poll->closes_at?->toIso8601String(),
                'condominium' => $poll->condominium?->name,
            ],
            'results' => $this->polls->results($poll),
            'statuses' => Poll::STATUSES,
        ]);
    }

    public function open(Request $request, Poll $poll): RedirectResponse
    {
        $poll = $this->authorizePoll($poll, $request);
        abort_unless($poll->options()->count() >= 2, 422, 'Adicione ao menos duas opções.');

        $poll->update(['status' => 'open']);
        $this->notifyResidents($poll);

        return back()->with('success', 'Enquete aberta para votação.');
    }

    public function close(Request $request, Poll $poll): RedirectResponse
    {
        $poll = $this->authorizePoll($poll, $request);
        $poll->update(['status' => 'closed']);

        return back()->with('success', 'Enquete encerrada.');
    }

    public function destroy(Request $request, Poll $poll): RedirectResponse
    {
        $poll = $this->authorizePoll($poll, $request);
        $poll->delete();

        return redirect()->route('polls.index')->with('success', 'Enquete removida.');
    }

    private function validatePoll(Request $request): array
    {
        $condominiumIds = $this->accessibleCondominiums(app('tenant')->id, $request->user())->pluck('id')->all();

        return $request->validate([
            'condominium_id' => ['required', 'uuid', Rule::in($condominiumIds)],
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'is_anonymous' => 'boolean',
            'closes_at' => 'nullable|date|after:now',
            'options' => 'required|array|min:2|max:10',
            'options.*' => 'required|string|max:150',
        ]);
    }

    private function authorizePoll(Poll $poll, Request $request): Poll
    {
        abort_unless($poll->tenant_id === app('tenant')->id, 403);
        $allowed = $this->accessibleCondominiums($poll->tenant_id, $request->user())->pluck('id')->all();
        abort_unless(in_array($poll->condominium_id, $allowed, true), 403);

        return $poll;
    }

    /** Avisa os moradores do condomínio que uma enquete foi aberta. */
    private function notifyResidents(Poll $poll): void
    {
        $personIds = PersonUnitLink::whereNull('end_date')
            ->join('units', 'units.id', '=', 'person_unit_links.unit_id')
            ->where('units.condominium_id', $poll->condominium_id)
            ->distinct()
            ->pluck('person_unit_links.person_id');

        if ($personIds->isEmpty()) {
            return;
        }

        $users = User::where('tenant_id', $poll->tenant_id)
            ->whereIn('person_id', $personIds)
            ->where('status', 'active')
            ->get();

        if ($users->isNotEmpty()) {
            Notification::send($users, new PollOpened($poll));
        }
    }

    private function condominiumOptions(Request $request): Collection
    {
        return $this->accessibleCondominiums(app('tenant')->id, $request->user())
            ->map(fn (Condominium $c) => ['value' => $c->id, 'label' => $c->name])
            ->values();
    }
}
