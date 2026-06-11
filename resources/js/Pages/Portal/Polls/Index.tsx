import PortalLayout from '@/Layouts/PortalLayout';
import { Head, Link } from '@inertiajs/react';
import { ListChecks } from 'lucide-react';

interface PollRow { id: string; title: string; status: 'open' | 'closed'; condominium: string | null }

export default function Index({ polls, statuses }: { polls: PollRow[]; statuses: Record<string, string> }) {
    return (
        <PortalLayout title="Enquetes">
            <Head title="Enquetes" />
            <div className="space-y-2">
                {polls.length === 0 && <p className="rounded-xl border border-gray-100 bg-white py-10 text-center text-sm text-gray-400">Nenhuma enquete no momento.</p>}
                {polls.map((p) => (
                    <Link key={p.id} href={route('portal.polls.show', p.id)} className="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-4 transition hover:border-blue-200">
                        <span className={`flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg ${p.status === 'open' ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-gray-400'}`}>
                            <ListChecks className="h-5 w-5" />
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="truncate font-medium text-gray-900">{p.title}</p>
                            <p className="truncate text-xs text-gray-500">{p.condominium}</p>
                        </div>
                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${p.status === 'open' ? 'bg-green-50 text-green-700' : 'bg-blue-50 text-blue-700'}`}>{statuses[p.status]}</span>
                    </Link>
                ))}
            </div>
        </PortalLayout>
    );
}
