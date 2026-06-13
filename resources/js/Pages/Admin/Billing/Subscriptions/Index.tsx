import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router } from '@inertiajs/react';

interface Subscription {
    id: string;
    status: string;
    value: string;
    billing_cycle: string;
    next_due_date: string | null;
    tenant: { id: string; name: string; status: string } | null;
    plan: { display_name: string } | null;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

interface Props {
    subscriptions: Paginated<Subscription>;
    filters: { status?: string; search?: string };
}

export const STATUS_LABELS: Record<string, { label: string; cls: string }> = {
    active: { label: 'Ativa', cls: 'bg-green-50 text-green-700' },
    overdue: { label: 'Inadimplente', cls: 'bg-amber-50 text-amber-700' },
    suspended: { label: 'Bloqueada', cls: 'bg-red-50 text-red-700' },
    grace_manual: { label: 'Carência (manual)', cls: 'bg-purple-50 text-purple-700' },
    grace_trust: { label: 'Carência (confiança)', cls: 'bg-indigo-50 text-indigo-700' },
    canceled: { label: 'Cancelada', cls: 'bg-gray-100 text-gray-500' },
};

const brl = (v: string) => `R$ ${parseFloat(v).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;

export default function SubscriptionsIndex({ subscriptions, filters }: Props) {
    const setStatus = (status: string) =>
        router.get('/admin/financeiro/assinaturas', { ...filters, status: status || undefined }, { preserveState: true, replace: true });

    return (
        <AdminLayout>
            <Head title="Financeiro — Assinaturas" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Assinaturas</h1>
                        <p className="mt-1 text-sm text-gray-500">{subscriptions.total} assinatura(s).</p>
                    </div>
                    <select
                        value={filters.status || ''}
                        onChange={(e) => setStatus(e.target.value)}
                        className="rounded-lg border border-gray-200 px-3 py-2 text-sm"
                    >
                        <option value="">Todos os status</option>
                        {Object.entries(STATUS_LABELS).map(([k, v]) => (
                            <option key={k} value={k}>{v.label}</option>
                        ))}
                    </select>
                </div>

                <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-gray-100">
                        <thead className="bg-gray-50">
                            <tr>
                                {['Tenant', 'Plano', 'Valor', 'Próx. vencimento', 'Status', ''].map((h) => (
                                    <th key={h} className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {subscriptions.data.length === 0 && (
                                <tr><td colSpan={6} className="px-4 py-12 text-center text-sm text-gray-500">Nenhuma assinatura.</td></tr>
                            )}
                            {subscriptions.data.map((s) => {
                                const st = STATUS_LABELS[s.status] ?? { label: s.status, cls: 'bg-gray-100 text-gray-600' };
                                return (
                                    <tr key={s.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm font-medium text-gray-900">{s.tenant?.name ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{s.plan?.display_name ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{brl(s.value)}<span className="text-xs text-gray-400">/{s.billing_cycle === 'yearly' ? 'ano' : 'mês'}</span></td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{s.next_due_date ?? '—'}</td>
                                        <td className="px-4 py-3"><span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${st.cls}`}>{st.label}</span></td>
                                        <td className="px-4 py-3 text-right">
                                            <Link href={`/admin/financeiro/assinaturas/${s.id}`} className="text-sm font-medium text-blue-600 hover:text-blue-700">Detalhes</Link>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap gap-1">
                    {subscriptions.links.map((l, i) => (
                        <button
                            key={i}
                            disabled={!l.url}
                            onClick={() => l.url && router.visit(l.url, { preserveState: true })}
                            className={`rounded px-3 py-1.5 text-sm ${l.active ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'} ${!l.url ? 'opacity-40' : ''}`}
                            dangerouslySetInnerHTML={{ __html: l.label }}
                        />
                    ))}
                </div>
            </div>
        </AdminLayout>
    );
}
