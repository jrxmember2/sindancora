import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { ListChecks, Plus } from 'lucide-react';

interface PollRow {
    id: string;
    title: string;
    status: 'draft' | 'open' | 'closed';
    condominium: string | null;
    votes_count: number;
    created_at: string | null;
}
interface Paginator<T> { data: T[]; links: { url: string | null; label: string; active: boolean }[] }

const badge: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-600',
    open: 'bg-green-50 text-green-700',
    closed: 'bg-blue-50 text-blue-700',
};

export default function Index({ polls, statuses }: { polls: Paginator<PollRow>; statuses: Record<string, string> }) {
    return (
        <AppLayout>
            <Head title="Enquetes" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Enquetes</h1>
                        <p className="mt-1 text-sm text-gray-500">Consultas rápidas aos moradores (um voto por pessoa).</p>
                    </div>
                    <Link href={route('polls.create')} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        <Plus className="h-4 w-4" /> Nova enquete
                    </Link>
                </div>

                <div className="space-y-2">
                    {polls.data.length === 0 && <p className="rounded-xl border border-gray-100 bg-white py-10 text-center text-sm text-gray-400">Nenhuma enquete ainda.</p>}
                    {polls.data.map((p) => (
                        <Link key={p.id} href={route('polls.show', p.id)} className="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-4 transition hover:border-blue-200">
                            <span className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                                <ListChecks className="h-5 w-5" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-medium text-gray-900">{p.title}</p>
                                <p className="truncate text-xs text-gray-500">{p.condominium} · {p.votes_count} voto(s)</p>
                            </div>
                            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${badge[p.status]}`}>{statuses[p.status]}</span>
                        </Link>
                    ))}
                </div>

                {polls.links.length > 3 && (
                    <div className="flex flex-wrap gap-1">
                        {polls.links.map((l, i) => (
                            <Link key={i} href={l.url ?? '#'} className={`rounded-lg px-3 py-1.5 text-sm ${l.active ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'} ${!l.url ? 'pointer-events-none text-gray-300' : ''}`} dangerouslySetInnerHTML={{ __html: l.label }} />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
