import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Inbox, UserPlus, AlertCircle, ChevronRight } from 'lucide-react';

interface Option { value: string; label: string }

interface Submission {
    id: string;
    type: string;
    status: string;
    name: string | null;
    phone: string | null;
    email: string | null;
    condominium: { id: string; name: string } | null;
    created_at: string | null;
}

interface Props {
    submissions: { data: Submission[]; links: { url: string | null; label: string; active: boolean }[] };
    condominiums: Option[];
    types: Record<string, string>;
    statuses: Record<string, string>;
    filters: { status: string; type: string | null; condominium_id: string | null };
    pendingCount: number;
}

const statusStyles: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
};

export default function ModerationIndex({ submissions, condominiums, types, statuses, filters }: Props) {
    const setFilter = (key: string, value: string) => {
        router.get(route('public-links.moderation.index'), { ...filters, [key]: value || undefined }, { preserveState: true, replace: true });
    };

    const fmt = (d: string | null) => (d ? new Date(d).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—');

    return (
        <AppLayout>
            <Head title="Moderação de envios públicos" />

            <div className="mb-6 flex items-center gap-3">
                <Link href={route('public-links.index')} className="text-sm text-gray-500 hover:text-gray-700">Links públicos</Link>
                <ChevronRight className="h-4 w-4 text-gray-300" />
                <h1 className="text-2xl font-bold text-gray-900">Moderação</h1>
            </div>

            <div className="mb-4 flex flex-wrap gap-3">
                <select value={filters.status} onChange={(e) => setFilter('status', e.target.value)} className="rounded-lg border-gray-300 text-sm">
                    {Object.entries(statuses).map(([v, t]) => <option key={v} value={v}>{t}</option>)}
                </select>
                <select value={filters.type ?? ''} onChange={(e) => setFilter('type', e.target.value)} className="rounded-lg border-gray-300 text-sm">
                    <option value="">Todos os tipos</option>
                    {Object.entries(types).map(([v, t]) => <option key={v} value={v}>{t}</option>)}
                </select>
                <select value={filters.condominium_id ?? ''} onChange={(e) => setFilter('condominium_id', e.target.value)} className="rounded-lg border-gray-300 text-sm">
                    <option value="">Todos os condomínios</option>
                    {condominiums.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                </select>
            </div>

            {submissions.data.length === 0 ? (
                <div className="rounded-xl border border-dashed border-gray-200 bg-white px-4 py-12 text-center">
                    <Inbox className="mx-auto mb-3 h-10 w-10 text-gray-300" />
                    <p className="text-sm text-gray-500">Nenhum envio neste filtro.</p>
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <ul className="divide-y divide-gray-100">
                        {submissions.data.map((s) => (
                            <li key={s.id}>
                                <Link href={route('public-links.moderation.show', s.id)} className="flex items-center gap-4 px-4 py-3 hover:bg-gray-50">
                                    <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full ${s.type === 'resident_signup' ? 'bg-blue-50 text-blue-600' : 'bg-amber-50 text-amber-600'}`}>
                                        {s.type === 'resident_signup' ? <UserPlus className="h-4 w-4" /> : <AlertCircle className="h-4 w-4" />}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium text-gray-900">{s.name ?? '—'}</p>
                                        <p className="truncate text-xs text-gray-500">{types[s.type]} · {s.condominium?.name ?? '—'} · {s.phone ?? s.email ?? '—'}</p>
                                    </div>
                                    <span className="hidden text-xs text-gray-400 sm:block">{fmt(s.created_at)}</span>
                                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusStyles[s.status] ?? 'bg-gray-100 text-gray-600'}`}>{statuses[s.status]}</span>
                                    <ChevronRight className="h-4 w-4 text-gray-300" />
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            {submissions.links.length > 3 && (
                <div className="mt-4 flex flex-wrap gap-1">
                    {submissions.links.map((l, i) => (
                        <button
                            key={i}
                            disabled={!l.url}
                            onClick={() => l.url && router.get(l.url, {}, { preserveState: true })}
                            className={`rounded-md px-3 py-1.5 text-sm ${l.active ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'} disabled:opacity-40`}
                            dangerouslySetInnerHTML={{ __html: l.label }}
                        />
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
