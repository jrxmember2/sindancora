import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertCircle, Plus, Search, Eye, Pencil, Trash2, BarChart3 } from 'lucide-react';
import { useState } from 'react';
import type { PageProps } from '@/types';

interface Option { value: string; label: string }
interface Occurrence {
    id: string; title: string; category: string; priority: string; status: string;
    due_at: string | null; sla_status: string | null;
    condominium: { id: string; name: string } | null;
    unit: { id: string; number: string } | null;
    assignee: { id: string; name: string } | null;
}
interface Props {
    occurrences: { data: Occurrence[] };
    condominiums: Option[];
    categories: Record<string, string>;
    priorities: Record<string, string>;
    statuses: Record<string, string>;
    filters: { search?: string; status?: string; category?: string; priority?: string; condominium_id?: string; sla?: string };
}

const slaBadge: Record<string, { label: string; cls: string }> = {
    overdue: { label: 'Atrasada', cls: 'bg-red-50 text-red-700' },
    due_soon: { label: 'Vence em breve', cls: 'bg-amber-50 text-amber-700' },
    on_time: { label: 'No prazo', cls: 'bg-green-50 text-green-700' },
};
const fmtDate = (iso: string | null) => (iso ? new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—');

const priorityStyle: Record<string, string> = {
    low: 'bg-gray-100 text-gray-600',
    normal: 'bg-blue-50 text-blue-700',
    high: 'bg-orange-50 text-orange-700',
    urgent: 'bg-red-50 text-red-700',
};
const statusStyle: Record<string, string> = {
    open: 'bg-amber-50 text-amber-700',
    in_progress: 'bg-blue-50 text-blue-700',
    closed: 'bg-green-50 text-green-700',
};

export default function OccurrencesIndex({ occurrences, condominiums, categories, priorities, statuses, filters }: Props) {
    const { auth } = usePage<PageProps>().props;
    const perms = auth.user?.permissions ?? [];
    const can = (p: string) => perms.includes('*') || perms.includes(p);

    const [search, setSearch] = useState(filters.search ?? '');
    const apply = (extra: Record<string, string> = {}) =>
        router.get(route('occurrences.index'), {
            search, status: filters.status ?? '', category: filters.category ?? '',
            priority: filters.priority ?? '', condominium_id: filters.condominium_id ?? '', sla: filters.sla ?? '', ...extra,
        }, { preserveState: true, replace: true });

    const destroy = (id: string, title: string) => {
        if (confirm(`Excluir a ocorrência "${title}"?`)) router.delete(route('occurrences.destroy', id));
    };

    return (
        <AppLayout>
            <Head title="Ocorrências" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <AlertCircle className="h-6 w-6 text-blue-600" />
                        <h1 className="text-2xl font-bold text-gray-900">Ocorrências</h1>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('occurrences.dashboard')} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                            <BarChart3 className="h-4 w-4" /> Painel
                        </Link>
                        {can('occurrences:create') && (
                            <Link href={route('occurrences.create')} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700">
                                <Plus className="h-4 w-4" /> Nova Ocorrência
                            </Link>
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap gap-3">
                    <div className="relative max-w-xs flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input value={search} onChange={e => setSearch(e.target.value)} onKeyDown={e => e.key === 'Enter' && apply()} placeholder="Buscar por título…" className="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                    </div>
                    <select value={filters.status ?? ''} onChange={e => apply({ status: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">Todos os status</option>
                        {Object.entries(statuses).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                    </select>
                    <select value={filters.priority ?? ''} onChange={e => apply({ priority: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">Todas as prioridades</option>
                        {Object.entries(priorities).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                    </select>
                    <select value={filters.category ?? ''} onChange={e => apply({ category: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">Todas as categorias</option>
                        {Object.entries(categories).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                    </select>
                    <select value={filters.sla ?? ''} onChange={e => apply({ sla: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">SLA: todos</option>
                        <option value="overdue">Atrasadas</option>
                    </select>
                </div>

                <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b border-gray-100 bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Título</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Local</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Categoria</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Prioridade</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Prazo</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Responsável</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {occurrences.data.length === 0 && (
                                <tr><td colSpan={8} className="px-4 py-8 text-center text-sm text-gray-500">Nenhuma ocorrência encontrada.</td></tr>
                            )}
                            {occurrences.data.map(o => (
                                <tr key={o.id} className="transition-colors hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium text-gray-900">{o.title}</td>
                                    <td className="px-4 py-3 text-xs text-gray-600">
                                        {o.condominium?.name ?? '—'}{o.unit ? ` · ${o.unit.number}` : ''}
                                    </td>
                                    <td className="px-4 py-3 text-gray-600">{categories[o.category] ?? o.category}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${priorityStyle[o.priority] ?? ''}`}>{priorities[o.priority] ?? o.priority}</span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusStyle[o.status] ?? ''}`}>{statuses[o.status] ?? o.status}</span>
                                    </td>
                                    <td className="px-4 py-3 text-xs">
                                        {o.sla_status && slaBadge[o.sla_status]
                                            ? <span className={`rounded-full px-2 py-0.5 font-medium ${slaBadge[o.sla_status].cls}`}>{slaBadge[o.sla_status].label}</span>
                                            : <span className="text-gray-500">{fmtDate(o.due_at)}</span>}
                                    </td>
                                    <td className="px-4 py-3 text-xs text-gray-600">{o.assignee?.name ?? <span className="text-gray-400">—</span>}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <Link href={route('occurrences.show', o.id)} className="rounded p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"><Eye className="h-4 w-4" /></Link>
                                            {can('occurrences:update') && (
                                                <Link href={route('occurrences.edit', o.id)} className="rounded p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"><Pencil className="h-4 w-4" /></Link>
                                            )}
                                            {can('occurrences:delete') && (
                                                <button onClick={() => destroy(o.id, o.title)} className="rounded p-1.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-500"><Trash2 className="h-4 w-4" /></button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
