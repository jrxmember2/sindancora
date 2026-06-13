import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, ExternalLink, FileText } from 'lucide-react';
import { STATUS_LABELS } from '../Subscriptions/Index';

interface Payment {
    id: string;
    asaas_payment_id: string;
    status: string;
    value: string;
    billing_type: string | null;
    due_date: string | null;
    payment_date: string | null;
    invoice_url: string | null;
    nfse_status: string | null;
    nfse_pdf_url: string | null;
}

interface TimelineEntry {
    id: string;
    type: string;
    description: string;
    actor_name: string | null;
    created_at: string;
}

interface Subscription {
    id: string;
    status: string;
    value: string;
    billing_cycle: string;
    billing_type: string;
    next_due_date: string | null;
    grace_until: string | null;
    grace_reason: string | null;
    trust_grace_count: number;
    tenant: { id: string; name: string; status: string; email: string | null; document: string | null } | null;
    plan: { display_name: string } | null;
}

interface Props {
    subscription: Subscription;
    payments: Payment[];
    timeline: TimelineEntry[];
}

const brl = (v: string | null) => (v == null ? '—' : `R$ ${parseFloat(v).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`);

export default function SubscriptionShow({ subscription, payments, timeline }: Props) {
    const [showGrace, setShowGrace] = useState(false);
    const st = STATUS_LABELS[subscription.status] ?? { label: subscription.status, cls: 'bg-gray-100 text-gray-600' };
    const base = `/admin/financeiro/assinaturas/${subscription.id}`;

    const grace = useForm({ reason: '', mode: 'date', until: '' });
    const inGrace = subscription.status === 'grace_manual' || subscription.status === 'grace_trust';

    const submitGrace = (e: React.FormEvent) => {
        e.preventDefault();
        grace.post(`${base}/desbloquear`, { onSuccess: () => { setShowGrace(false); grace.reset(); } });
    };

    const action = (path: string, confirmMsg: string) => {
        if (confirm(confirmMsg)) router.post(`${base}/${path}`);
    };

    return (
        <AdminLayout>
            <Head title={`Assinatura — ${subscription.tenant?.name ?? ''}`} />

            <div className="space-y-6">
                <Link href="/admin/financeiro/assinaturas" className="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700">
                    <ArrowLeft className="h-4 w-4" /> Voltar
                </Link>

                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">{subscription.tenant?.name}</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            {subscription.plan?.display_name} · {brl(subscription.value)}/{subscription.billing_cycle === 'yearly' ? 'ano' : 'mês'} · {subscription.billing_type}
                        </p>
                    </div>
                    <span className={`inline-flex rounded-full px-3 py-1 text-sm font-medium ${st.cls}`}>{st.label}</span>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <Card title="Pagamentos / Faturas">
                            <table className="min-w-full divide-y divide-gray-100 text-sm">
                                <thead><tr className="text-left text-xs uppercase text-gray-400">
                                    <th className="py-2">Vencimento</th><th>Valor</th><th>Status</th><th>NFS-e</th><th></th>
                                </tr></thead>
                                <tbody className="divide-y divide-gray-100">
                                    {payments.length === 0 && <tr><td colSpan={5} className="py-6 text-center text-gray-400">Sem pagamentos.</td></tr>}
                                    {payments.map((p) => (
                                        <tr key={p.id}>
                                            <td className="py-2 text-gray-600">{p.due_date ?? '—'}</td>
                                            <td className="text-gray-900">{brl(p.value)}</td>
                                            <td><span className="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{p.status}</span></td>
                                            <td>{p.nfse_status ? <span className={`text-xs ${p.nfse_status === 'error' ? 'text-red-600' : 'text-gray-500'}`}>{p.nfse_status}</span> : '—'}</td>
                                            <td className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    {p.invoice_url && <a href={p.invoice_url} target="_blank" rel="noreferrer" className="text-blue-600" title="Fatura"><ExternalLink className="h-4 w-4" /></a>}
                                                    {p.nfse_pdf_url && <a href={p.nfse_pdf_url} target="_blank" rel="noreferrer" className="text-gray-500" title="NFS-e"><FileText className="h-4 w-4" /></a>}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </Card>

                        <Card title="Linha do tempo">
                            <ul className="space-y-3">
                                {timeline.length === 0 && <li className="text-sm text-gray-400">Sem eventos.</li>}
                                {timeline.map((t) => (
                                    <li key={t.id} className="flex gap-3 text-sm">
                                        <span className="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full bg-blue-400" />
                                        <div>
                                            <p className="text-gray-700">{t.description}</p>
                                            <p className="text-xs text-gray-400">
                                                {new Date(t.created_at).toLocaleString('pt-BR')}{t.actor_name ? ` · ${t.actor_name}` : ''}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card title="Ações">
                            {inGrace && (
                                <div className="mb-3 rounded-lg bg-purple-50 p-3 text-xs text-purple-700">
                                    Em carência até <strong>{subscription.grace_until}</strong>.
                                    {subscription.grace_reason && <p className="mt-1">{subscription.grace_reason}</p>}
                                </div>
                            )}
                            <div className="flex flex-col gap-2">
                                {(subscription.status === 'suspended' || subscription.status === 'overdue') && (
                                    <button onClick={() => setShowGrace(true)} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                        Desbloquear manualmente
                                    </button>
                                )}
                                {inGrace && (
                                    <button onClick={() => action('revogar-carencia', 'Revogar a carência e bloquear o tenant?')} className="rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                                        Revogar carência (bloquear)
                                    </button>
                                )}
                                {subscription.status !== 'suspended' && subscription.status !== 'canceled' && (
                                    <button onClick={() => action('bloquear', 'Bloquear o tenant agora?')} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        Bloquear manualmente
                                    </button>
                                )}
                                {subscription.status !== 'canceled' && (
                                    <button onClick={() => action('cancelar', 'Cancelar a assinatura no Asaas e localmente?')} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        Cancelar assinatura
                                    </button>
                                )}
                            </div>
                        </Card>

                        <Card title="Cliente">
                            <dl className="space-y-2 text-sm">
                                <Row label="E-mail" value={subscription.tenant?.email} />
                                <Row label="Documento" value={subscription.tenant?.document} />
                                <Row label="Próx. vencimento" value={subscription.next_due_date} />
                                <Row label="Carências por confiança" value={String(subscription.trust_grace_count)} />
                            </dl>
                        </Card>
                    </div>
                </div>
            </div>

            {showGrace && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={() => setShowGrace(false)}>
                    <form onClick={(e) => e.stopPropagation()} onSubmit={submitGrace} className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                        <h3 className="text-lg font-bold text-gray-900">Desbloqueio manual</h3>
                        <p className="mt-1 text-sm text-gray-500">O tenant fica liberado em carência, sinalizado no painel.</p>

                        <label className="mt-4 block text-sm font-medium text-gray-700">Motivo</label>
                        <textarea required value={grace.data.reason} onChange={(e) => grace.setData('reason', e.target.value)} className="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" rows={2} />
                        {grace.errors.reason && <span className="text-xs text-red-600">{grace.errors.reason}</span>}

                        <label className="mt-4 block text-sm font-medium text-gray-700">Prazo</label>
                        <select value={grace.data.mode} onChange={(e) => grace.setData('mode', e.target.value)} className="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                            <option value="date">Data limite</option>
                            <option value="next_due">Até o próximo vencimento</option>
                        </select>
                        {grace.data.mode === 'date' && (
                            <input type="date" value={grace.data.until} onChange={(e) => grace.setData('until', e.target.value)} className="mt-2 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                        )}
                        {grace.errors.until && <span className="text-xs text-red-600">{grace.errors.until}</span>}

                        <div className="mt-6 flex justify-end gap-2">
                            <button type="button" onClick={() => setShowGrace(false)} className="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">Cancelar</button>
                            <button type="submit" disabled={grace.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Confirmar desbloqueio</button>
                        </div>
                    </form>
                </div>
            )}
        </AdminLayout>
    );
}

function Card({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <h2 className="mb-4 text-sm font-semibold text-gray-700">{title}</h2>
            {children}
        </div>
    );
}

function Row({ label, value }: { label: string; value: string | null | undefined }) {
    return (
        <div className="flex justify-between gap-4">
            <dt className="text-gray-400">{label}</dt>
            <dd className="text-right font-medium text-gray-700">{value || '—'}</dd>
        </div>
    );
}
