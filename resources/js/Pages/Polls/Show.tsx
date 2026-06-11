import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { Play, Square, Trash2 } from 'lucide-react';

interface ResultOption { id: string; label: string; votes: number; percent: number }
interface Poll {
    id: string;
    title: string;
    description: string | null;
    status: 'draft' | 'open' | 'closed';
    is_anonymous: boolean;
    closes_at: string | null;
    condominium: string | null;
}
interface Props {
    poll: Poll;
    results: { total_votes: number; options: ResultOption[] };
    statuses: Record<string, string>;
}

export default function Show({ poll, results, statuses }: Props) {
    const open = () => router.post(route('polls.open', poll.id), {}, { preserveScroll: true });
    const close = () => router.post(route('polls.close', poll.id), {}, { preserveScroll: true });
    const destroy = () => { if (confirm('Excluir esta enquete?')) router.delete(route('polls.destroy', poll.id)); };

    return (
        <AppLayout>
            <Head title={poll.title} />
            <div className="mx-auto max-w-2xl space-y-6">
                <div>
                    <span className="text-xs font-medium uppercase tracking-wide text-gray-400">{poll.condominium}</span>
                    <h1 className="text-2xl font-bold text-gray-900">{poll.title}</h1>
                    {poll.description && <p className="mt-1 text-sm text-gray-500">{poll.description}</p>}
                    <span className={`mt-2 inline-block rounded-full px-2 py-0.5 text-xs font-medium ${poll.status === 'open' ? 'bg-green-50 text-green-700' : poll.status === 'closed' ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-600'}`}>
                        {statuses[poll.status]}{poll.is_anonymous ? ' · anônima' : ''}
                    </span>
                </div>

                <div className="space-y-3 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <p className="text-sm font-medium text-gray-700">{results.total_votes} voto(s)</p>
                    {results.options.map((o) => (
                        <div key={o.id}>
                            <div className="mb-1 flex justify-between text-sm">
                                <span className="text-gray-700">{o.label}</span>
                                <span className="text-gray-500">{o.votes} · {o.percent}%</span>
                            </div>
                            <div className="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                                <div className="h-full rounded-full bg-blue-500" style={{ width: `${o.percent}%` }} />
                            </div>
                        </div>
                    ))}
                </div>

                <div className="flex flex-wrap gap-2">
                    {poll.status === 'draft' && (
                        <button onClick={open} className="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                            <Play className="h-4 w-4" /> Abrir votação
                        </button>
                    )}
                    {poll.status === 'open' && (
                        <button onClick={close} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                            <Square className="h-4 w-4" /> Encerrar
                        </button>
                    )}
                    <button onClick={destroy} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                        <Trash2 className="h-4 w-4" /> Excluir
                    </button>
                </div>
            </div>
        </AppLayout>
    );
}
