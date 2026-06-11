import PortalLayout from '@/Layouts/PortalLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Check } from 'lucide-react';

interface ResultOption { id: string; label: string; votes: number; percent: number }
interface Poll {
    id: string;
    title: string;
    description: string | null;
    status: 'open' | 'closed';
    is_anonymous: boolean;
    condominium: string | null;
}
interface Props {
    poll: Poll;
    results: { total_votes: number; options: ResultOption[]; my_vote: string | null };
    statuses: Record<string, string>;
}

export default function Show({ poll, results }: Props) {
    const [choice, setChoice] = useState<string | null>(results.my_vote);
    const canVote = poll.status === 'open';
    const hasVoted = results.my_vote !== null;
    const showResults = !canVote || hasVoted;

    const vote = () => {
        if (!choice) return;
        router.post(route('portal.polls.vote', poll.id), { option_id: choice }, { preserveScroll: true });
    };

    return (
        <PortalLayout title={poll.title}>
            <Head title={poll.title} />
            <div className="space-y-5">
                {poll.description && <p className="text-sm text-gray-600">{poll.description}</p>}

                <div className="space-y-3 rounded-xl border border-gray-100 bg-white p-5">
                    {results.options.map((o) => {
                        const isMine = results.my_vote === o.id;
                        return (
                            <button
                                key={o.id}
                                type="button"
                                disabled={!canVote || hasVoted}
                                onClick={() => setChoice(o.id)}
                                className={`w-full rounded-lg border p-3 text-left transition ${choice === o.id ? 'border-blue-400 ring-1 ring-blue-300' : 'border-gray-200'} ${(!canVote || hasVoted) ? 'cursor-default' : 'hover:border-blue-300'}`}
                            >
                                <div className="flex items-center justify-between text-sm">
                                    <span className="font-medium text-gray-800">
                                        {o.label} {isMine && <Check className="ml-1 inline h-4 w-4 text-green-600" />}
                                    </span>
                                    {showResults && <span className="text-gray-500">{o.votes} · {o.percent}%</span>}
                                </div>
                                {showResults && (
                                    <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-100">
                                        <div className={`h-full rounded-full ${isMine ? 'bg-green-500' : 'bg-blue-500'}`} style={{ width: `${o.percent}%` }} />
                                    </div>
                                )}
                            </button>
                        );
                    })}
                </div>

                {canVote && !hasVoted && (
                    <button onClick={vote} disabled={!choice} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        Votar
                    </button>
                )}
                {showResults && (
                    <p className="text-xs text-gray-400">{results.total_votes} voto(s){hasVoted ? ' · você já votou' : ''}{poll.status === 'closed' ? ' · enquete encerrada' : ''}</p>
                )}
            </div>
        </PortalLayout>
    );
}
