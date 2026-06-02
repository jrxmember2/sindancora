import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search, Eye, Layers, Wallet, AlertTriangle, CheckCircle2 } from 'lucide-react';
import { useState } from 'react';

interface Charge {
    id: string; description: string; type: string; reference_month: string | null;
    amount: string; due_date: string; status: string;
    condominium: { name: string } | null; unit: { number: string } | null; person: { name: string } | null;
}
interface Props {
    charges: { data: Charge[] };
    kpis: { open: string | number; overdue: string | number; received_month: string | number };
    condominiums: { value: string; label: string }[];
    types: Record<string, string>;
    statuses: Record<string, string>;
    filters: { condominium_id?: string; status?: string; reference_month?: string; search?: string };
}

const brl = (v: string | number) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const statusStyles: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700',
    paid: 'bg-green-100 text-green-700',
    overdue: 'bg-red-100 text-red-700',
    cancelled: 'bg-gray-100 text-gray-600',
};

export default function ChargesIndex({ charges, kpis, condominiums, statuses, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const apply = (params: Record<string, string>) => router.get(route('charges.index'), { ...filters, ...params }, { preserveState: true, replace: true });

    return (
        <AppLayout>
            <Head title="Cobranças" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-bold text-gray-900">Cobranças</h1>
                <div className="flex gap-2">
                    <Link href={route('charges.generate')} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <Layers className="h-4 w-4" /> Gerar em lote
                    </Link>
                    <Link href={route('charges.create')} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        <Plus className="h-4 w-4" /> Nova cobrança
                    </Link>
                </div>
            </div>

            {/* KPIs */}
            <div className="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div className="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600"><Wallet className="h-5 w-5" /></span>
                    <div><p className="text-lg font-bold text-gray-900">{brl(kpis.open)}</p><p className="text-xs text-gray-500">Em aberto</p></div>
                </div>
                <div className="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 text-red-600"><AlertTriangle className="h-5 w-5" /></span>
                    <div><p className="text-lg font-bold text-gray-900">{brl(kpis.overdue)}</p><p className="text-xs text-gray-500">Vencido</p></div>
                </div>
                <div className="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50 text-green-600"><CheckCircle2 className="h-5 w-5" /></span>
                    <div><p className="text-lg font-bold text-gray-900">{brl(kpis.received_month)}</p><p className="text-xs text-gray-500">Recebido no mês</p></div>
                </div>
            </div>

            {/* Filtros */}
            <div className="mb-4 flex flex-col gap-2 sm:flex-row">
                <form onSubmit={(e) => { e.preventDefault(); apply({ search }); }} className="relative flex-1">
                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Buscar descrição…" className="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                </form>
                <select value={filters.condominium_id ?? ''} onChange={(e) => apply({ condominium_id: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="">Todos os condomínios</option>
                    {condominiums.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                </select>
                <select value={filters.status ?? ''} onChange={(e) => apply({ status: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="">Todos os status</option>
                    {Object.entries(statuses).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
                <input type="month" value={filters.reference_month ?? ''} onChange={(e) => apply({ reference_month: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
            </div>

            {/* Tabela */}
            <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <table className="w-full text-sm">
                    <thead className="border-b border-gray-100 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th className="px-4 py-3">Descrição</th>
                            <th className="px-4 py-3">Unidade</th>
                            <th className="px-4 py-3">Vencimento</th>
                            <th className="px-4 py-3 text-right">Valor</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50">
                        {charges.data.length === 0 && (
                            <tr><td colSpan={6} className="px-4 py-10 text-center text-gray-400">Nenhuma cobrança encontrada.</td></tr>
                        )}
                        {charges.data.map((c) => (
                            <tr key={c.id} className="hover:bg-gray-50">
                                <td className="px-4 py-3">
                                    <p className="font-medium text-gray-900">{c.description}</p>
                                    <p className="text-xs text-gray-500">{c.condominium?.name}{c.reference_month ? ` · ${c.reference_month}` : ''}</p>
                                </td>
                                <td className="px-4 py-3 text-gray-600">{c.unit?.number ?? '—'}{c.person?.name ? <span className="block text-xs text-gray-400">{c.person.name}</span> : null}</td>
                                <td className="px-4 py-3 text-gray-600">{new Date(c.due_date + 'T00:00:00').toLocaleDateString('pt-BR')}</td>
                                <td className="px-4 py-3 text-right font-medium text-gray-900">{brl(c.amount)}</td>
                                <td className="px-4 py-3"><span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${statusStyles[c.status] ?? 'bg-gray-100 text-gray-600'}`}>{statuses[c.status] ?? c.status}</span></td>
                                <td className="px-4 py-3 text-right">
                                    <Link href={route('charges.show', c.id)} className="inline-flex rounded p-1 text-gray-400 hover:text-blue-600" title="Ver"><Eye className="h-4 w-4" /></Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
