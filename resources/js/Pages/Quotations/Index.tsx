import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ClipboardCheck, Pencil, Plus, Trash2 } from 'lucide-react';
import type { PageProps } from '@/types';
import type { Option } from './QuotationForm';

interface Quotation {
    id: string;
    title: string;
    category: string | null;
    status: string;
    status_label: string;
    response_deadline: string | null;
    proposals_count: number;
    created_at: string;
    condominium: { id: string; name: string } | null;
    approved_proposal: { supplier_name: string; amount: string } | null;
}

interface Props {
    quotations: {
        data: Quotation[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    summary: { collecting: number; draft: number; approved: number; proposals_total: number };
    categories: Record<string, string>;
    statuses: Record<string, string>;
    condominiums: Option[];
    filters: { status?: string; condominium_id?: string; category?: string; search?: string };
}

const brl = (value: string | number) => Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const fmtDate = (value: string | null) => value ? new Date(value.slice(0, 10) + 'T00:00:00').toLocaleDateString('pt-BR') : '-';

const statusClass: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-600',
    collecting: 'bg-blue-50 text-blue-700',
    approved: 'bg-emerald-50 text-emerald-700',
    rejected: 'bg-red-50 text-red-700',
    cancelled: 'bg-gray-100 text-gray-500',
};

function Stat({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
            <p className="text-xs font-medium uppercase tracking-wide text-gray-500">{label}</p>
            <p className="mt-1 text-xl font-bold text-gray-900">{value}</p>
        </div>
    );
}

export default function QuotationsIndex({ quotations, summary, categories, statuses, condominiums, filters }: Props) {
    const { auth } = usePage<PageProps>().props;
    const permissions = auth.user?.permissions ?? [];
    const can = (permission: string) => permissions.includes('*') || permissions.includes(permission);

    const apply = (params: Record<string, string>) => router.get(
        route('quotations.index'),
        { ...filters, ...params },
        { preserveState: true, replace: true },
    );

    const clearFilters = () => router.get(route('quotations.index'), {}, { replace: true });

    const destroy = (quotation: Quotation) => {
        if (confirm(`Remover o orçamento "${quotation.title}"?`)) {
            router.delete(route('quotations.destroy', quotation.id));
        }
    };

    return (
        <AppLayout>
            <Head title="Orçamentos" />
            <div className="space-y-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2">
                        <ClipboardCheck className="h-6 w-6 text-blue-600" />
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Orçamentos</h1>
                            <p className="text-sm text-gray-500">Cotações multi-fornecedor, comparação e aprovação.</p>
                        </div>
                    </div>
                    {can('quotations:create') && (
                        <Link href={route('quotations.create')} className="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                            <Plus className="h-4 w-4" /> Novo orçamento
                        </Link>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
                    <Stat label="Em cotação" value={summary.collecting} />
                    <Stat label="Rascunhos" value={summary.draft} />
                    <Stat label="Aprovados" value={summary.approved} />
                    <Stat label="Propostas" value={summary.proposals_total} />
                </div>

                <div className="flex flex-col gap-2 rounded-lg border border-gray-100 bg-white p-3 shadow-sm lg:flex-row lg:flex-wrap">
                    <input value={filters.search ?? ''} onChange={(e) => apply({ search: e.target.value })} placeholder="Buscar por título..." className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    <select value={filters.status ?? ''} onChange={(e) => apply({ status: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">Todos os status</option>
                        {Object.entries(statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                    <select value={filters.category ?? ''} onChange={(e) => apply({ category: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">Todas as categorias</option>
                        {Object.entries(categories).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                    <select value={filters.condominium_id ?? ''} onChange={(e) => apply({ condominium_id: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">Todos os condomínios</option>
                        {condominiums.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                    </select>
                    <button onClick={clearFilters} className="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Limpar</button>
                </div>

                <div className="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b border-gray-100 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Orçamento</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Prazo</th>
                                <th className="px-4 py-3">Propostas</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {quotations.data.length === 0 && (
                                <tr><td colSpan={5} className="px-4 py-10 text-center text-sm text-gray-400">Nenhum orçamento encontrado.</td></tr>
                            )}
                            {quotations.data.map((quotation) => (
                                <tr key={quotation.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <Link href={route('quotations.show', quotation.id)} className="font-medium text-gray-900 hover:text-blue-600">{quotation.title}</Link>
                                        <p className="text-xs text-gray-500">
                                            {quotation.condominium?.name ?? '-'}
                                            {quotation.category ? ` · ${categories[quotation.category] ?? quotation.category}` : ''}
                                        </p>
                                        {quotation.approved_proposal && (
                                            <p className="mt-1 text-xs text-emerald-700">
                                                Aprovado: {quotation.approved_proposal.supplier_name} · {brl(quotation.approved_proposal.amount)}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex rounded-full px-2 py-1 text-xs font-medium ${statusClass[quotation.status] ?? 'bg-gray-100 text-gray-600'}`}>
                                            {quotation.status_label}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-gray-600">{fmtDate(quotation.response_deadline)}</td>
                                    <td className="px-4 py-3 text-gray-600">{quotation.proposals_count}</td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            {can('quotations:update') && quotation.status !== 'approved' && (
                                                <Link href={route('quotations.edit', quotation.id)} className="rounded p-1 text-gray-400 hover:text-blue-600" title="Editar">
                                                    <Pencil className="h-4 w-4" />
                                                </Link>
                                            )}
                                            {can('quotations:delete') && quotation.status !== 'approved' && (
                                                <button onClick={() => destroy(quotation)} className="rounded p-1 text-gray-400 hover:text-red-500" title="Remover">
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

                {quotations.links.length > 3 && (
                    <div className="flex flex-wrap gap-1">
                        {quotations.links.map((link, index) => (
                            <button key={index} disabled={!link.url} onClick={() => link.url && router.visit(link.url)}
                                className={`rounded px-3 py-1.5 text-sm ${link.active ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'} disabled:opacity-40`}
                                dangerouslySetInnerHTML={{ __html: link.label }} />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
