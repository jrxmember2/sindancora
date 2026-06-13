import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link } from '@inertiajs/react';
import { CreditCard, Users, AlertCircle, Ban, HeartHandshake, TrendingUp, FileWarning, Clock } from 'lucide-react';

interface Metrics {
    mrr: number;
    active: number;
    overdue: number;
    suspended: number;
    grace: number;
    canceled: number;
    revenue_month: number;
    churn: number;
    pending_signups: number;
    failed_signups: number;
    nfse_errors: number;
}

interface Props {
    metrics: Metrics;
    revenueSeries: { label: string; value: number }[];
}

const brl = (v: number) => `R$ ${v.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;

export default function BillingDashboard({ metrics, revenueSeries }: Props) {
    const maxRevenue = Math.max(1, ...revenueSeries.map((r) => r.value));

    const cards = [
        { label: 'MRR', value: brl(metrics.mrr), icon: TrendingUp, color: 'text-blue-600 bg-blue-50' },
        { label: 'Receita do mês', value: brl(metrics.revenue_month), icon: CreditCard, color: 'text-green-600 bg-green-50' },
        { label: 'Assinaturas ativas', value: String(metrics.active), icon: Users, color: 'text-gray-700 bg-gray-100' },
        { label: 'Inadimplentes', value: String(metrics.overdue), icon: AlertCircle, color: 'text-amber-600 bg-amber-50' },
        { label: 'Bloqueados', value: String(metrics.suspended), icon: Ban, color: 'text-red-600 bg-red-50' },
        { label: 'Em carência', value: String(metrics.grace), icon: HeartHandshake, color: 'text-purple-600 bg-purple-50' },
        { label: 'Churn (mês)', value: `${metrics.churn}%`, icon: TrendingUp, color: 'text-rose-600 bg-rose-50' },
        { label: 'Cadastros pendentes', value: String(metrics.pending_signups), icon: Clock, color: 'text-slate-600 bg-slate-100' },
    ];

    return (
        <AdminLayout>
            <Head title="Financeiro — Dashboard" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Financeiro</h1>
                        <p className="mt-1 text-sm text-gray-500">Visão geral do billing SaaS (Asaas).</p>
                    </div>
                    <div className="flex gap-2">
                        <Link href="/admin/financeiro/assinaturas" className="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Assinaturas</Link>
                        <Link href="/admin/financeiro/pagamentos" className="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Pagamentos</Link>
                        <Link href="/admin/financeiro/configuracoes" className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Configurações</Link>
                    </div>
                </div>

                {(metrics.failed_signups > 0 || metrics.nfse_errors > 0) && (
                    <div className="flex flex-wrap gap-3">
                        {metrics.failed_signups > 0 && (
                            <div className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-800">
                                <FileWarning className="h-4 w-4" /> {metrics.failed_signups} provisionamento(s) com falha — verifique os cadastros.
                            </div>
                        )}
                        {metrics.nfse_errors > 0 && (
                            <div className="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800">
                                <FileWarning className="h-4 w-4" /> {metrics.nfse_errors} NFS-e com erro de emissão.
                            </div>
                        )}
                    </div>
                )}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {cards.map((c) => {
                        const Icon = c.icon;
                        return (
                            <div key={c.label} className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-gray-500">{c.label}</span>
                                    <span className={`flex h-8 w-8 items-center justify-center rounded-lg ${c.color}`}>
                                        <Icon className="h-4 w-4" />
                                    </span>
                                </div>
                                <p className="mt-3 text-2xl font-bold text-gray-900">{c.value}</p>
                            </div>
                        );
                    })}
                </div>

                <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 className="text-sm font-semibold text-gray-700">Receita dos últimos 6 meses</h2>
                    <div className="mt-6 flex items-end gap-4" style={{ height: 180 }}>
                        {revenueSeries.map((r) => (
                            <div key={r.label} className="flex flex-1 flex-col items-center justify-end gap-2">
                                <span className="text-xs font-medium text-gray-600">{brl(r.value)}</span>
                                <div
                                    className="w-full rounded-t bg-blue-500"
                                    style={{ height: `${Math.max(4, (r.value / maxRevenue) * 130)}px` }}
                                />
                                <span className="text-xs text-gray-400">{r.label}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
