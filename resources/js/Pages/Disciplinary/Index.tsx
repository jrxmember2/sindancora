import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Plus, XCircle } from 'lucide-react';

interface RecordRow {
    id: string;
    type: 'warning' | 'fine';
    status: 'issued' | 'acknowledged' | 'cancelled';
    title: string;
    rule_reference: string | null;
    condominium: string | null;
    unit: string | null;
    person: string | null;
    issued_at: string | null;
    amount: number | null;
    due_date: string | null;
    charge_id: string | null;
}

interface Paginator<T> { data: T[]; links: { url: string | null; label: string; active: boolean }[] }

interface Props {
    records: Paginator<RecordRow>;
    types: Record<string, string>;
    statuses: Record<string, string>;
    filters: { type: string | null; status: string | null; search: string | null };
}

const statusBadge: Record<string, string> = {
    issued: 'bg-amber-50 text-amber-700',
    acknowledged: 'bg-blue-50 text-blue-700',
    cancelled: 'bg-gray-100 text-gray-600',
};

const money = (value: number | null) => value === null ? '-' : value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function Index({ records, types, statuses, filters }: Props) {
    const setFilter = (key: string, value: string) => router.get(route('disciplinary.index'), { ...filters, [key]: value || undefined }, { preserveScroll: true, preserveState: true, replace: true });
    const cancel = (id: string) => {
        const reason = prompt('Motivo do cancelamento (opcional):');
        if (reason === null) return;
        router.post(route('disciplinary.cancel', id), { reason }, { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title="Multas e advertencias" />
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Multas e advertencias</h1>
                        <p className="mt-1 text-sm text-gray-500">Registros regimentais por unidade, com ciencia do morador e cobranca opcional.</p>
                    </div>
                    <Link href={route('disciplinary.create')} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        <Plus className="h-4 w-4" /> Novo registro
                    </Link>
                </div>

                <div className="flex flex-wrap gap-3">
                    <input
                        value={filters.search ?? ''}
                        onChange={(e) => setFilter('search', e.target.value)}
                        className="w-64 rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                        placeholder="Buscar por titulo ou regra"
                    />
                    <select value={filters.type ?? ''} onChange={(e) => setFilter('type', e.target.value)} className="rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Todos os tipos</option>
                        {Object.entries(types).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                    </select>
                    <select value={filters.status ?? ''} onChange={(e) => setFilter('status', e.target.value)} className="rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Todos os status</option>
                        {Object.entries(statuses).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                    </select>
                </div>

                <div className="space-y-2">
                    {records.data.length === 0 && <p className="rounded-xl border border-gray-100 bg-white py-10 text-center text-sm text-gray-400">Nenhum registro.</p>}
                    {records.data.map((record) => (
                        <div key={record.id} className="flex gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                            <span className={`flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-lg ${record.type === 'fine' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-600'}`}>
                                <AlertTriangle className="h-5 w-5" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-700">{types[record.type]}</span>
                                    <span className={`rounded-full px-2 py-0.5 text-[11px] font-medium ${statusBadge[record.status]}`}>{statuses[record.status]}</span>
                                    {record.charge_id && <span className="rounded-full bg-green-50 px-2 py-0.5 text-[11px] font-medium text-green-700">Com cobranca</span>}
                                </div>
                                <Link href={route('disciplinary.show', record.id)} className="mt-1 block truncate font-medium text-gray-900 hover:text-blue-700">{record.title}</Link>
                                <p className="truncate text-xs text-gray-500">{record.condominium} - {record.unit}{record.person ? ` - ${record.person}` : ''}</p>
                                <p className="mt-1 text-xs text-gray-400">{record.rule_reference || 'Sem artigo informado'} {record.type === 'fine' ? `- ${money(record.amount)} - venc. ${record.due_date ?? '-'}` : ''}</p>
                            </div>
                            {record.status !== 'cancelled' && (
                                <button onClick={() => cancel(record.id)} className="self-start rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Cancelar">
                                    <XCircle className="h-5 w-5" />
                                </button>
                            )}
                        </div>
                    ))}
                </div>

                {records.links.length > 3 && (
                    <div className="flex flex-wrap gap-1">
                        {records.links.map((link, index) => (
                            <Link key={index} href={link.url ?? '#'} className={`rounded-lg px-3 py-1.5 text-sm ${link.active ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'} ${!link.url ? 'pointer-events-none text-gray-300' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
