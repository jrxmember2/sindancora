import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, FileText, Receipt, XCircle } from 'lucide-react';

interface Attachment { id: string; name: string; size: number }
interface RecordData {
    id: string;
    type: 'warning' | 'fine';
    status: 'issued' | 'acknowledged' | 'cancelled';
    title: string;
    rule_reference: string | null;
    description: string;
    condominium: string | null;
    unit: string | null;
    person: string | null;
    occurred_on: string | null;
    issued_at: string | null;
    acknowledged_at: string | null;
    amount: number | null;
    due_date: string | null;
    charge_id: string | null;
    charge_status: string | null;
    cancellation_reason: string | null;
    attachments: Attachment[];
}

const money = (value: number | null) => value === null ? '-' : value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function Show({ record, types, statuses, canGenerateCharge }: { record: RecordData; types: Record<string, string>; statuses: Record<string, string>; canGenerateCharge: boolean }) {
    const cancel = () => {
        const reason = prompt('Motivo do cancelamento (opcional):');
        if (reason === null) return;
        router.post(route('disciplinary.cancel', record.id), { reason }, { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title={record.title} />
            <div className="mx-auto max-w-4xl space-y-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <Link href={route('disciplinary.index')} className="text-sm text-blue-600 hover:text-blue-700">Voltar</Link>
                        <h1 className="mt-1 text-2xl font-bold text-gray-900">{record.title}</h1>
                        <p className="text-sm text-gray-500">{record.condominium} - {record.unit}{record.person ? ` - ${record.person}` : ''}</p>
                    </div>
                    <div className="flex gap-2">
                        {canGenerateCharge && (
                            <button onClick={() => router.post(route('disciplinary.charge', record.id), {}, { preserveScroll: true })} className="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                                <Receipt className="h-4 w-4" /> Gerar cobranca
                            </button>
                        )}
                        {record.status !== 'cancelled' && (
                            <button onClick={cancel} className="inline-flex items-center gap-2 rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                                <XCircle className="h-4 w-4" /> Cancelar
                            </button>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <p className="text-xs uppercase text-gray-400">Tipo</p>
                        <p className="mt-1 font-semibold text-gray-900">{types[record.type]}</p>
                    </div>
                    <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <p className="text-xs uppercase text-gray-400">Status</p>
                        <p className="mt-1 font-semibold text-gray-900">{statuses[record.status]}</p>
                    </div>
                    <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <p className="text-xs uppercase text-gray-400">Valor</p>
                        <p className="mt-1 font-semibold text-gray-900">{money(record.amount)}</p>
                    </div>
                </div>

                <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div className="mb-4 flex items-center gap-2 text-gray-700">
                        <AlertTriangle className="h-5 w-5" />
                        <span className="font-semibold">Detalhes regimentais</span>
                    </div>
                    <dl className="grid gap-4 text-sm sm:grid-cols-2">
                        <div><dt className="text-gray-400">Regra</dt><dd className="font-medium text-gray-900">{record.rule_reference || '-'}</dd></div>
                        <div><dt className="text-gray-400">Data do fato</dt><dd className="font-medium text-gray-900">{record.occurred_on || '-'}</dd></div>
                        <div><dt className="text-gray-400">Vencimento</dt><dd className="font-medium text-gray-900">{record.due_date || '-'}</dd></div>
                        <div><dt className="text-gray-400">Ciencia</dt><dd className="font-medium text-gray-900">{record.acknowledged_at || '-'}</dd></div>
                    </dl>
                    <p className="mt-5 whitespace-pre-wrap text-sm leading-6 text-gray-700">{record.description}</p>
                    {record.cancellation_reason && <p className="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">Cancelamento: {record.cancellation_reason}</p>}
                    {record.charge_id && (
                        <Link href={route('charges.show', record.charge_id)} className="mt-4 inline-flex items-center gap-2 text-sm font-medium text-blue-600 hover:text-blue-700">
                            <Receipt className="h-4 w-4" /> Ver cobranca vinculada
                        </Link>
                    )}
                </div>

                {record.attachments.length > 0 && (
                    <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h2 className="mb-3 font-semibold text-gray-900">Anexos</h2>
                        <div className="space-y-2">
                            {record.attachments.map((attachment) => (
                                <a key={attachment.id} href={route('attachments.download', attachment.id)} className="flex items-center gap-2 rounded-lg border border-gray-100 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <FileText className="h-4 w-4 text-gray-400" /> {attachment.name}
                                </a>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
