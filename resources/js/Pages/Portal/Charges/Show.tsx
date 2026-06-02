import PortalLayout from '@/Layouts/PortalLayout';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Download, QrCode, FileText, Mail, Copy, Check } from 'lucide-react';
import { useState } from 'react';

interface Charge {
    id: string; description: string; type: string; reference_month: string | null;
    amount: string; current_amount: number; due_date: string; status: string;
    fine_rate: string; interest_rate: string;
    paid_at: string | null; paid_amount: string | null; payment_method: string | null; notes: string | null;
    condominium: { name: string } | null; unit: { number: string } | null; receipt: { id: string } | null;
    gateway_payment_id: string | null; invoice_url: string | null;
    bank_slip_url: string | null; bank_slip_line: string | null;
    pix_payload: string | null; pix_qrcode: string | null;
}
interface Props {
    charge: Charge;
    types: Record<string, string>;
    statuses: Record<string, string>;
    gatewayEnabled: boolean;
}

const brl = (v: string | number | null) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const statusStyles: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700',
    overdue: 'bg-red-100 text-red-700',
    paid: 'bg-green-100 text-green-700',
    cancelled: 'bg-gray-100 text-gray-600',
};

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

export default function PortalChargeShow({ charge, types, statuses, gatewayEnabled }: Props) {
    const overdue = charge.status === 'overdue';
    const open = ['pending', 'overdue'].includes(charge.status);
    const hasGateway = !!charge.gateway_payment_id;

    const secondCopy = () => router.post(route('portal.charges.second-copy', charge.id), {}, { preserveScroll: true });

    return (
        <PortalLayout>
            <Head title={charge.description} />

            <Link href={route('portal.charges.index')} className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <ArrowLeft className="h-4 w-4" /> Minhas cobranças
            </Link>

            <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <div className="flex items-start justify-between gap-3 border-b border-gray-100 p-5">
                    <div>
                        <h1 className="text-lg font-bold text-gray-900">{charge.description}</h1>
                        <p className="text-xs text-gray-500">
                            {charge.condominium?.name} · Unid. {charge.unit?.number ?? '—'} · {types[charge.type] ?? charge.type}
                        </p>
                    </div>
                    <span className={`flex-shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold ${statusStyles[charge.status] ?? 'bg-gray-100 text-gray-600'}`}>
                        {statuses[charge.status] ?? charge.status}
                    </span>
                </div>

                <dl className="grid grid-cols-2 gap-4 p-5 text-sm">
                    <div><dt className="text-gray-500">Valor</dt><dd className="font-medium text-gray-900">{brl(charge.amount)}</dd></div>
                    <div><dt className="text-gray-500">Vencimento</dt><dd className="font-medium text-gray-900">{new Date(charge.due_date + 'T00:00:00').toLocaleDateString('pt-BR')}</dd></div>
                    {overdue && charge.current_amount > Number(charge.amount) && (
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

                {/* Pagamento online (boleto + PIX) */}
                {open && hasGateway && (
                    <div className="border-t border-gray-100 p-5">
                        <div className="mb-3 flex items-center gap-2">
                            <QrCode className="h-4 w-4 text-blue-600" />
                            <h2 className="text-sm font-semibold text-gray-900">Pagar com boleto ou PIX</h2>
                        </div>
                        <div className="space-y-4">
                            {charge.pix_qrcode && (
                                <div className="flex flex-col items-center gap-2">
                                    <img src={`data:image/png;base64,${charge.pix_qrcode}`} alt="QR Code PIX" className="h-44 w-44" />
                                    <p className="text-xs text-gray-500">Aponte a câmera do app do banco</p>
                                </div>
                            )}
                            {charge.pix_payload && <CopyLine label="PIX copia-e-cola" value={charge.pix_payload} />}
                            {charge.bank_slip_line && <CopyLine label="Linha digitável do boleto" value={charge.bank_slip_line} />}
                            <div className="flex flex-wrap gap-2">
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
                                <button onClick={secondCopy} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    <Mail className="h-4 w-4" /> Receber 2ª via por e-mail
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {/* Sem boleto emitido ainda: oferecer 2ª via (emite sob demanda) */}
                {open && !hasGateway && gatewayEnabled && (
                    <div className="border-t border-gray-100 p-5">
                        <button onClick={secondCopy} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                            <Mail className="h-4 w-4" /> Receber boleto/PIX por e-mail
                        </button>
                    </div>
                )}

                {charge.receipt && (
                    <div className="border-t border-gray-100 p-5">
                        <a href={route('portal.charges.download', charge.id)} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <Download className="h-4 w-4" /> Baixar comprovante
                        </a>
                    </div>
                )}
            </div>
        </PortalLayout>
    );
}
