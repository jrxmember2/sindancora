import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { FileDown, FileSpreadsheet, TrendingUp, TrendingDown, Wallet, AlertTriangle } from 'lucide-react';

interface Month { month: string; label: string; charged: number; received: number; expenses: number }
interface Delinquent { unit: string; person: string; count: number; total: number }
interface Report {
    summary: { charged: number; received: number; open: number; expenses: number; balance: number; overdue_total: number; delinquent_units: number };
    delinquents: Delinquent[];
    months: Month[];
}
interface Props {
    report: Report;
    condominiums: { value: string; label: string }[];
    filters: { condominium_id: string | null; from: string; to: string };
    canExport: boolean;
}

const brl = (v: number) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function FinancialReport({ report, condominiums, filters, canExport }: Props) {
    const apply = (params: Record<string, string>) => router.get(route('reports.index'), { ...filters, ...params }, { preserveState: true, replace: true });

    const qs = new URLSearchParams({
        ...(filters.condominium_id ? { condominium_id: filters.condominium_id } : {}),
        from: filters.from, to: filters.to,
    }).toString();

    const s = report.summary;

    return (
        <AppLayout>
            <Head title="Relatórios financeiros" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-bold text-gray-900">Relatórios financeiros</h1>
                {canExport && (
                    <div className="flex gap-2">
                        <a href={`${route('reports.pdf')}?${qs}`} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <FileDown className="h-4 w-4" /> PDF
                        </a>
                        <a href={`${route('reports.xlsx')}?${qs}`} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <FileSpreadsheet className="h-4 w-4" /> Excel
                        </a>
                    </div>
                )}
            </div>

            {/* Filtros */}
            <div className="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end">
                <div>
                    <label className="block text-xs font-medium text-gray-500">Condomínio</label>
                    <select value={filters.condominium_id ?? ''} onChange={(e) => apply({ condominium_id: e.target.value })} className="mt-1 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="">Todos</option>
                        {condominiums.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                    </select>
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-500">De</label>
                    <input type="date" value={filters.from} onChange={(e) => apply({ from: e.target.value })} className="mt-1 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-500">Até</label>
                    <input type="date" value={filters.to} onChange={(e) => apply({ to: e.target.value })} className="mt-1 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                </div>
            </div>

            {/* Cards */}
            <div className="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <div className="flex items-center gap-2 text-green-600"><TrendingUp className="h-4 w-4" /><span className="text-xs font-medium text-gray-500">Recebido</span></div>
                    <p className="mt-1 text-lg font-bold text-gray-900">{brl(s.received)}</p>
                </div>
                <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <div className="flex items-center gap-2 text-red-600"><TrendingDown className="h-4 w-4" /><span className="text-xs font-medium text-gray-500">Despesas</span></div>
                    <p className="mt-1 text-lg font-bold text-gray-900">{brl(s.expenses)}</p>
                </div>
                <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <div className="flex items-center gap-2 text-blue-600"><Wallet className="h-4 w-4" /><span className="text-xs font-medium text-gray-500">Saldo</span></div>
                    <p className={`mt-1 text-lg font-bold ${s.balance >= 0 ? 'text-gray-900' : 'text-red-600'}`}>{brl(s.balance)}</p>
                </div>
                <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <div className="flex items-center gap-2 text-amber-600"><AlertTriangle className="h-4 w-4" /><span className="text-xs font-medium text-gray-500">Inadimplência</span></div>
                    <p className="mt-1 text-lg font-bold text-gray-900">{brl(s.overdue_total)}</p>
                    <p className="text-[11px] text-gray-400">{s.delinquent_units} unidade(s)</p>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {/* Mensal */}
                <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <h2 className="border-b border-gray-100 px-4 py-3 text-sm font-semibold text-gray-900">Movimentação mensal</h2>
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr><th className="px-4 py-2">Mês</th><th className="px-4 py-2 text-right">Cobrado</th><th className="px-4 py-2 text-right">Recebido</th><th className="px-4 py-2 text-right">Despesas</th></tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {report.months.map((m) => (
                                <tr key={m.month}>
                                    <td className="px-4 py-2 capitalize text-gray-700">{m.label}</td>
                                    <td className="px-4 py-2 text-right text-gray-600">{brl(m.charged)}</td>
                                    <td className="px-4 py-2 text-right text-green-700">{brl(m.received)}</td>
                                    <td className="px-4 py-2 text-right text-red-600">{brl(m.expenses)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Inadimplentes */}
                <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <h2 className="border-b border-gray-100 px-4 py-3 text-sm font-semibold text-gray-900">Inadimplência por unidade</h2>
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr><th className="px-4 py-2">Unidade</th><th className="px-4 py-2">Responsável</th><th className="px-4 py-2 text-right">Devido</th></tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {report.delinquents.length === 0 && (
                                <tr><td colSpan={3} className="px-4 py-8 text-center text-gray-400">Sem inadimplência. 🎉</td></tr>
                            )}
                            {report.delinquents.map((d, i) => (
                                <tr key={i}>
                                    <td className="px-4 py-2 font-medium text-gray-900">{d.unit}</td>
                                    <td className="px-4 py-2 text-gray-600">{d.person}<span className="block text-[11px] text-gray-400">{d.count} cobrança(s)</span></td>
                                    <td className="px-4 py-2 text-right font-medium text-red-600">{brl(d.total)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
