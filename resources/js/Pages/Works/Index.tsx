import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Hammer, Pencil, Plus, Receipt, Search, Trash2, Wallet } from 'lucide-react';
import type { PageProps } from '@/types';
import type { Option } from './WorkForm';

interface Work {
    id: string;
    title: string;
    type: string;
    type_label: string;
    status: string;
    status_label: string;
    priority_label: string;
    start_date: string | null;
    expected_end_date: string | null;
    budget_amount: string | null;
    final_amount: string | null;
    progress_percent: number;
    updates_count: number;
    expenses_total_amount: string | number | null;
    open_expenses_total_amount: string | number | null;
    condominium: { id: string; name: string } | null;
    supplier: { id: string; name: string } | null;
    quotation: { id: string; title: string } | null;
}

interface Summary {
    active: number;
    in_progress: number;
    completed: number;
    budget_total: number;
    open_expenses_total: number;
}

interface Props {
    works: {
        data: Work[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    summary: Summary;
    types: Record<string, string>;
    statuses: Record<string, string>;
    condominiums: Option[];
    suppliers: Option[];
    filters: {
        status?: string;
        type?: string;
        condominium_id?: string;
        supplier_id?: string;
        search?: string;
    };
}

const brl = (value: string | number | null) => Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const date = (value: string | null) => value ? new Date(value.slice(0, 10) + 'T00:00:00').toLocaleDateString('pt-BR') : '-';

const statusClass: Record<string, string> = {
    planned: 'bg-gray-100 text-gray-600',
    budgeting: 'bg-amber-50 text-amber-700',
    approved: 'bg-blue-50 text-blue-700',
    in_progress: 'bg-emerald-50 text-emerald-700',
    paused: 'bg-orange-50 text-orange-700',
    completed: 'bg-slate-100 text-slate-700',
    cancelled: 'bg-red-50 text-red-700',
};

function SummaryCard({ label, value, icon: Icon }: { label: string; value: string | number; icon: React.ElementType }) {
    return (
        <div className="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <p className="text-xs font-medium uppercase text-gray-500">{label}</p>
                    <p className="mt-1 text-lg font-bold text-gray-900">{value}</p>
                </div>
                <div className="rounded-lg bg-blue-600 p-2 text-white">
                    <Icon className="h-5 w-5" />
                </div>
            </div>
        </div>
    );
}

export default function WorksIndex({ works, summary, types, statuses, condominiums, suppliers, filters }: Props) {
    const { auth } = usePage<PageProps>().props;
    const permissions = auth.user?.permissions ?? [];
    const can = (permission: string) => permissions.includes('*') || permissions.includes(permission);
    const [search, setSearch] = useState(filters.search ?? '');

    const apply = (params: Record<string, string>) => router.get(
        route('works.index'),
        { ...filters, ...params },
        { preserveState: true, replace: true },
    );
    const clearFilters = () => {
        setSearch('');
        router.get(route('works.index'), {}, { replace: true });
    };
    const destroy = (work: Work) => {
        if (confirm(`Remover "${work.title}"?`)) {
            router.delete(route('works.destroy', work.id));
        }
    };

    return (
        <AppLayout>
            <Head title="Obras/Reformas" />

            <div className="space-y-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2">
                        <Hammer className="h-6 w-6 text-blue-600" />
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Obras/Reformas</h1>
                            <p className="text-sm text-gray-500">Acompanhamento de escopo, fornecedores, orçamento e contas vinculadas.</p>
                        </div>
                    </div>
                    {can('works:create') && (
                        <Link href={route('works.create')} className="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                            <Plus className="h-4 w-4" /> Nova obra
                        </Link>
                    )}
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <SummaryCard label="Ativas" value={summary.active} icon={Hammer} />
                    <SummaryCard label="Em execução" value={summary.in_progress} icon={Hammer} />
                    <SummaryCard label="Concluídas" value={summary.completed} icon={Hammer} />
                    <SummaryCard label="Orçado" value={brl(summary.budget_total)} icon={Wallet} />
                    <SummaryCard label="Contas abertas" value={brl(summary.open_expenses_total)} icon={Receipt} />
                </div>

                <div className="flex flex-col gap-2 rounded-lg border border-gray-100 bg-white p-3 shadow-sm xl:flex-row xl:flex-wrap">
                    <select value={filters.status ?? ''} onChange={(e) => apply({ status: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">Todos os status</option>
                        {Object.entries(statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                    <select value={filters.type ?? ''} onChange={(e) => apply({ type: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">Todos os tipos</option>
                        {Object.entries(types).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                    <select value={filters.condominium_id ?? ''} onChange={(e) => apply({ condominium_id: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">Todos os condomínios</option>
                        {condominiums.map((condominium) => <option key={condominium.value} value={condominium.value}>{condominium.label}</option>)}
                    </select>
                    <select value={filters.supplier_id ?? ''} onChange={(e) => apply({ supplier_id: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">Todos os fornecedores</option>
                        {suppliers.map((supplier) => <option key={supplier.value} value={supplier.value}>{supplier.label}</option>)}
                    </select>
                    <div className="flex min-w-[240px] flex-1 rounded-lg border border-gray-200 bg-white focus-within:border-blue-500">
                        <Search className="ml-3 mt-2.5 h-4 w-4 text-gray-400" />
                        <input value={search} onChange={(e) => setSearch(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && apply({ search })} placeholder="Buscar obra" className="w-full border-0 px-2 py-2 text-sm focus:outline-none focus:ring-0" />
                    </div>
                    <button onClick={() => apply({ search })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Buscar</button>
                    <button onClick={clearFilters} className="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Limpar</button>
                </div>

                <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b border-gray-100 bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Obra</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Período</th>
                                <th className="px-4 py-3 text-right">Orçamento</th>
                                <th className="px-4 py-3 text-right">Contas abertas</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {works.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-10 text-center text-gray-400">
                                        <Hammer className="mx-auto mb-2 h-8 w-8 text-gray-300" />
                                        Nenhuma obra/reforma encontrada.
                                    </td>
                                </tr>
                            )}
                            {works.data.map((work) => (
                                <tr key={work.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <Link href={route('works.show', work.id)} className="font-medium text-gray-900 hover:text-blue-600">{work.title}</Link>
                                        <p className="text-xs text-gray-500">
                                            {work.condominium?.name ?? '-'} · {work.type_label}
                                            {work.supplier ? ` · ${work.supplier.name}` : ''}
                                        </p>
                                        {work.quotation && <p className="mt-1 text-xs text-gray-400">Origem: {work.quotation.title}</p>}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex rounded-full px-2 py-1 text-xs font-medium ${statusClass[work.status] ?? 'bg-gray-100 text-gray-600'}`}>{work.status_label}</span>
                                        <div className="mt-2 h-1.5 w-28 overflow-hidden rounded-full bg-gray-100">
                                            <div className="h-full rounded-full bg-blue-600" style={{ width: `${Math.min(Math.max(work.progress_percent, 0), 100)}%` }} />
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-xs text-gray-600">
                                        <span className="block">{date(work.start_date)}</span>
                                        <span className="block text-gray-400">{date(work.expected_end_date)}</span>
                                    </td>
                                    <td className="px-4 py-3 text-right font-medium text-gray-900">{brl(work.budget_amount)}</td>
                                    <td className="px-4 py-3 text-right text-gray-700">{brl(work.open_expenses_total_amount)}</td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            {can('works:update') && (
                                                <Link href={route('works.edit', work.id)} className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-blue-600" title="Editar">
                                                    <Pencil className="h-4 w-4" />
                                                </Link>
                                            )}
                                            {can('works:delete') && (
                                                <button onClick={() => destroy(work)} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-500" title="Remover">
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {works.links.length > 3 && (
                    <div className="flex flex-wrap gap-1">
                        {works.links.map((link, index) => (
                            <button
                                key={index}
                                disabled={!link.url}
                                onClick={() => link.url && router.visit(link.url)}
                                className={`rounded px-3 py-1.5 text-sm ${link.active ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'} disabled:opacity-40`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
