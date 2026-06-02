import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Pencil, Ban, CheckCircle2, Download, X, QrCode, FileText, Mail, Copy, Check } from 'lucide-react';
import { useState } from 'react';

interface Charge {
    id: string; description: string; type: string; reference_month: string | null;
    amount: string; current_amount: number; due_date: string; status: string;
    fine_rate: string; interest_rate: string;
    paid_at: string | null; paid_amount: string | null; payment_method: string | null; notes: string | null;
    condominium: { name: string } | null; unit: { number: string } | null; person: { name: string } | null;
    creator: { name: string } | null; receipt: { id: string } | null;
    gateway_payment_id: string | null; invoice_url: string | null;
    bank_slip_url: string | null; bank_slip_line: string | null;
    pix_payload: string | null; pix_qrcode: string | null;
}
interface Props {
    charge: Charge;
    types: Record<string, string>;
    statuses: Record<string, string>;
    canMarkPaid: boolean;
    canUpdate: boolean;
    gatewayEnabled: boolean;
}

const brl = (v: string | number | null) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const statusStyles: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700',
    paid: 'bg-green-100 text-green-700',
    overdue: 'bg-red-100 text-red-700',
    cancelled: 'bg-gray-100 text-gray-600',
};

function PaymentModal({ charge, onClose }: { charge: Charge; onClose: () => void }) {
    const { data, setData, post, processing, errors } = useForm({
        paid_at: new Date().toISOString().slice(0, 10),
        paid_amount: String(charge.current_amount),
        payment_method: 'pix',
        notes: '',
        receipt: null as File | null,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('charges.pay', charge.id), { forceFormData: true, onSuccess: onClose });
    };

    const field = 'mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <form onSubmit={submit} className="w-full max-w-md space-y-4 rounded-2xl bg-white p-6 shadow-xl">
                <div className="flex items-center justify-between">
                    <h2 className="text-base font-semibold text-gray-900">Registrar pagamento</h2>
                    <button type="button" onClick={onClose}><X className="h-5 w-5 text-gray-400 hover:text-gray-600" /></button>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Data *</label>
                        <input type="date" value={data.paid_at} onChange={(e) => setData('paid_at', e.target.value)} className={field} />
                        {errors.paid_at && <p className="mt-1 text-xs text-red-600">{errors.paid_at}</p>}
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Valor pago (R$) *</label>
                        <input type="number" step="0.01" min="0" value={data.paid_amount} onChange={(e) => setData('paid_amount', e.target.value)} className={field} />
                        {errors.paid_amount && <p className="mt-1 text-xs text-red-600">{errors.paid_amount}</p>}
                    </div>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Forma de pagamento</label>
                    <select value={data.payment_method} onChange={(e) => setData('payment_method', e.target.value)} className={field}>
                        <option value="pix">PIX</option>
                        <option value="boleto">Boleto</option>
                        <option value="transfer">Transferência</option>
                        <option value="cash">Dinheiro</option>
                        <option value="card">Cartão</option>
                        <option value="other">Outro</option>
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Comprovante (opcional)</label>
                    <input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" onChange={(e) => setData('receipt', e.target.files?.[0] ?? null)} className="mt-1 w-full text-sm text-gray-600" />
                    {errors.receipt && <p className="mt-1 text-xs text-red-600">{errors.receipt}</p>}
                </div>
                <div className="flex justify-end gap-2 pt-1">
                    <button type="button" onClick={onClose} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button type="submit" disabled={processing} className="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">
                        {processing ? 'Salvando…' : 'Confirmar pagamento'}
                    </button>
                </div>
            </form>
        </div>
    );
}

function CopyLine({ value, label }: { value: string; label: string }) {
    const [copied, setCopied] = useState(false);
    const copy = () => {
        navigator.clipboard.writeText(value);
        setCopied(true);
        setTimeout(() => setCopied(false), 1500);
    };
    return (
        <div>
            <p className="text-xs text-gray-500">{label}</p>
            <div className="mt-1 flex gap-2">
                <input readOnly value={value} className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-xs text-gray-700" />
                <button onClick={copy} className="rounded-lg border border-gray-200 p-2 text-gray-500 hover:bg-gray-50">
                    {copied ? <Check className="h-4 w-4 text-green-600" /> : <Copy className="h-4 w-4" />}
                </button>
            </div>
        </div>
    );
}

export default function ChargeShow({ charge, types, statuses, canMarkPaid, canUpdate, gatewayEnabled }: Props) {
    const [payOpen, setPayOpen] = useState(false);
    const open = ['pending', 'overdue'].includes(charge.status);
    const hasGateway = !!charge.gateway_payment_id;

    const cancel = () => {
        if (confirm('Cancelar esta cobrança?')) router.delete(route('charges.destroy', charge.id));
    };

    const issue = () => router.post(route('charges.issue', charge.id), {}, { preserveScroll: true });
    const secondCopy = () => router.post(route('charges.second-copy', charge.id), {}, { preserveScroll: true });

    return (
        <AppLayout>
            <Head title={charge.description} />
            {payOpen && <PaymentModal charge={charge} onClose={() => setPayOpen(false)} />}

            <div className="mb-4">
                <Link href={route('charges.index')} className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                    <ArrowLeft className="h-4 w-4" /> Cobranças
                </Link>
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                        <div className="flex items-start justify-between gap-3 border-b border-gray-100 p-5">
                            <div>
                                <h1 className="text-lg font-bold text-gray-900">{charge.description}</h1>
                                <p className="text-xs text-gray-500">
                                    {charge.condominium?.name} · Unid. {charge.unit?.number ?? '—'}
                                    {charge.person?.name ? ` · ${charge.person.name}` : ''}
                                </p>
                            </div>
                            <span className={`flex-shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold ${statusStyles[charge.status] ?? 'bg-gray-100 text-gray-600'}`}>
                                {statuses[charge.status] ?? charge.status}
                            </span>
                        </div>
                        <dl className="grid grid-cols-2 gap-4 p-5 text-sm">
                            <div><dt className="text-gray-500">Tipo</dt><dd className="font-medium text-gray-900">{types[charge.type] ?? charge.type}</dd></div>
                            <div><dt className="text-gray-500">Mês de referência</dt><dd className="font-medium text-gray-900">{charge.reference_month ?? '—'}</dd></div>
                            <div><dt className="text-gray-500">Valor original</dt><dd className="font-medium text-gray-900">{brl(charge.amount)}</dd></div>
                            <div><dt className="text-gray-500">Vencimento</dt><dd className="font-medium text-gray-900">{new Date(charge.due_date + 'T00:00:00').toLocaleDateString('pt-BR')}</dd></div>
                            {open && charge.current_amount > Number(charge.amount) && (
                                <div className="col-span-2 rounded-lg bg-red-50 p-3">
                                    <dt className="text-xs text-red-600">Valor atualizado (multa {charge.fine_rate}% + juros {charge.interest_rate}%/mês)</dt>
                                    <dd className="text-lg font-bold text-red-700">{brl(charge.current_amount)}</dd>
                                </div>
                            )}
                            {charge.status === 'paid' && (
                                <>
                                    <div><dt className="text-gray-500">Pago em</dt><dd className="font-medium text-gray-900">{charge.paid_at ? new Date(charge.paid_at).toLocaleDateString('pt-BR') : '—'}</dd></div>
                                    <div><dt className="text-gray-500">Valor pago</dt><dd className="font-medium text-green-700">{brl(charge.paid_amount)}</dd></div>
                                </>
                            )}
                            {charge.notes && <div className="col-span-2"><dt className="text-gray-500">Observações</dt><dd className="text-gray-700">{charge.notes}</dd></div>}
                        </dl>
                    </div>

                    {hasGateway && open && (
                        <div className="mt-6 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                            <div className="flex items-center gap-2 border-b border-gray-100 p-5">
                                <QrCode className="h-4 w-4 text-blue-600" />
                                <h2 className="text-sm font-semibold text-gray-900">Boleto + PIX (Asaas)</h2>
                            </div>
                            <div className="space-y-4 p-5">
                                {charge.pix_qrcode && (
                                    <div className="flex flex-col items-center gap-2">
                                        <img src={`data:image/png;base64,${charge.pix_qrcode}`} alt="QR Code PIX" className="h-44 w-44" />
                                        <p className="text-xs text-gray-500">Aponte a câmera do app do banco</p>
                                    </div>
                                )}
                                {charge.pix_payload && <CopyLine label="PIX copia-e-cola" value={charge.pix_payload} />}
                                {charge.bank_slip_line && <CopyLine label="Linha digitável do boleto" value={charge.bank_slip_line} />}
                                <div className="flex flex-wrap gap-2 pt-1">
                                    {charge.invoice_url && (
                                        <a href={charge.invoice_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                            <FileText className="h-4 w-4" /> Abrir fatura
                                        </a>
                                    )}
                                    {charge.bank_slip_url && (
                                        <a href={charge.bank_slip_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                            <Download className="h-4 w-4" /> Baixar boleto (PDF)
                                        </a>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                {/* Ações */}
                <div className="space-y-3">
                    {canUpdate && gatewayEnabled && open && (
                        <button onClick={issue} className="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
                            <QrCode className="h-4 w-4" /> {hasGateway ? 'Atualizar boleto/PIX' : 'Gerar boleto/PIX'}
                        </button>
                    )}
                    {canUpdate && gatewayEnabled && open && hasGateway && (
                        <button onClick={secondCopy} className="flex w-full items-center justify-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <Mail className="h-4 w-4" /> Enviar 2ª via por e-mail
                        </button>
                    )}
                    {canMarkPaid && open && (
                        <button onClick={() => setPayOpen(true)} className="flex w-full items-center justify-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-green-700">
                            <CheckCircle2 className="h-4 w-4" /> Registrar pagamento manual
                        </button>
                    )}
                    {charge.receipt && (
                        <a href={route('charges.download', charge.id)} className="flex w-full items-center justify-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <Download className="h-4 w-4" /> Baixar comprovante
                        </a>
                    )}
                    {canUpdate && open && (
                        <Link href={route('charges.edit', charge.id)} className="flex w-full items-center justify-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <Pencil className="h-4 w-4" /> Editar
                        </Link>
                    )}
                    {canUpdate && open && (
                        <button onClick={cancel} className="flex w-full items-center justify-center gap-2 rounded-lg border border-red-200 px-4 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50">
                            <Ban className="h-4 w-4" /> Cancelar cobrança
                        </button>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
