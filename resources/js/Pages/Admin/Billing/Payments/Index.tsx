import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router } from '@inertiajs/react';
import { ExternalLink, FileText } from 'lucide-react';

interface Payment {
    id: string;
    status: string;
    value: string;
    billing_type: string | null;
    due_date: string | null;
    payment_date: string | null;
    invoice_url: string | null;
    nfse_status: string | null;
    nfse_pdf_url: string | null;
    nfse_error: string | null;
    tenant: { id: string; name: string } | null;
}

interface Paginated<T> { data: T[]; links: { url: string | null; label: string; active: boolean }[]; total: number; }

interface Props {
    payments: Paginated<Payment>;
    filters: { status?: string; nfse?: string; search?: string };
}

const brl = (v: string) => `R$ ${parseFloat(v).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;

export default function PaymentsIndex({ payments, filters }: Props) {
    const update = (patch: Record<string, string | undefined>) =>
        router.get('/admin/financeiro/pagamentos', { ...filters, ...patch }, { preserveState: true, replace: true });

    return (
        <AdminLayout>
            <Head title="Financeiro — Pagamentos" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Pagamentos / Faturas</h1>
                        <p className="mt-1 text-sm text-gray-500">{payments.total} registro(s). Espelho do Asaas.</p>
                    </div>
                    <div className="flex gap-2">
                        <input
                            defaultValue={filters.search}
                            onKeyDown={(e) => e.key === 'Enter' && update({ search: (e.target as HTMLInputElement).value || undefined })}
                            placeholder="Buscar tenant…"
                            className="rounded-lg border border-gray-200 px-3 py-2 text-sm"
                        />
                        <select value={filters.nfse || ''} onChange={(e) => update({ nfse: e.target.value || undefined })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm">
                            <option value="">NFS-e (todas)</option>
                            <option value="scheduled">Agendada</option>
                            <option value="authorized">Emitida</option>
                            <option value="error">Erro</option>
                        </select>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-gray-100">
                        <thead className="bg-gray-50">
                            <tr>{['Tenant', 'Vencimento', 'Pago em', 'Valor', 'Método', 'Status', 'NFS-e', ''].map((h) => (
                                <th key={h} className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">{h}</th>
                            ))}</tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {payments.data.length === 0 && <tr><td colSpan={8} className="px-4 py-12 text-center text-sm text-gray-500">Nenhum pagamento.</td></tr>}
                            {payments.data.map((p) => (
                                <tr key={p.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 text-sm font-medium text-gray-900">{p.tenant?.name ?? '—'}</td>
                                    <td className="px-4 py-3 text-sm text-gray-600">{p.due_date ?? '—'}</td>
                                    <td className="px-4 py-3 text-sm text-gray-600">{p.payment_date ?? '—'}</td>
                                    <td className="px-4 py-3 text-sm text-gray-900">{brl(p.value)}</td>
                                    <td className="px-4 py-3 text-sm text-gray-600">{p.billing_type ?? '—'}</td>
                                    <td className="px-4 py-3"><span className="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{p.status}</span></td>
                                    <td className="px-4 py-3 text-sm">
                                        {p.nfse_status
                                            ? <span title={p.nfse_error ?? ''} className={p.nfse_status === 'error' ? 'text-red-600' : 'text-gray-500'}>{p.nfse_status}</span>
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-2">
                                            {p.invoice_url && <a href={p.invoice_url} target="_blank" rel="noreferrer" className="text-blue-600" title="Fatura"><ExternalLink className="h-4 w-4" /></a>}
                                            {p.nfse_pdf_url && <a href={p.nfse_pdf_url} target="_blank" rel="noreferrer" className="text-gray-500" title="NFS-e"><FileText className="h-4 w-4" /></a>}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap gap-1">
                    {payments.links.map((l, i) => (
                        <button key={i} disabled={!l.url} onClick={() => l.url && router.visit(l.url, { preserveState: true })}
                            className={`rounded px-3 py-1.5 text-sm ${l.active ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'} ${!l.url ? 'opacity-40' : ''}`}
                            dangerouslySetInnerHTML={{ __html: l.label }} />
                    ))}
                </div>
            </div>
        </AdminLayout>
    );
}
