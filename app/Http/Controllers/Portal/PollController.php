<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\InteractsWithResident;
use App\Models\Poll;
use App\Services\PollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Enquetes no portal do morador: lista as enquetes dos seus condomínios e registra o voto (1 por pessoa).
 */
class PollController extends Controller
{
    use InteractsWithResident;

    public function __construct(private readonly PollService $polls) {}

    public function index(): Response
    {
        $polls = Poll::with('condominium:id,name')
            ->whereIn('condominium_id', $this->condominiumIds())
            ->whereIn('status', ['open', 'closed'])
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (Poll $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'status' => $p->status,
                'condominium' => $p->condominium?->name,
            ]);

        return Inertia::render('Portal/Polls/Index', [
            'polls' => $polls,
            'statuses' => Poll::STATUSES,
        ]);
    }

    public function show(Poll $poll): Response
    {
        $this->authorizePoll($poll);
        $poll->load('condominium:id,name');

        return Inertia::render('Portal/Polls/Show', [
            'poll' => [
                'id' => $poll->id,
                'title' => $poll->title,
                'description' => $poll->description,
                'status' => $poll->status,
                'is_anonymous' => $poll->is_anonymous,
                'condominium' => $poll->condominium?->name,
            ],
            'results' => $this->polls->results($poll, $this->resident()->id),
            'statuses' => Poll::STATUSES,
        ]);
    }

    public function vote(Request $request, Poll $poll): RedirectResponse
    {
        $this->authorizePoll($poll);
        $data = $request->validate(['option_id' => 'required|uuid']);

        $this->polls->castVote($poll, $data['option_id'], $this->resident()->id);

        return back()->with('success', 'Voto registrado.');
    }

    private function authorizePoll(Poll $poll): void
    {
        abort_unless(in_array($poll->condominium_id, $this->condominiumIds(), true), 403);
    }
}
