import PortalLayout from '@/Layouts/PortalLayout';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, FileText } from 'lucide-react';

interface Attachment { id: string; name: string }
interface RecordData {
    id: string;
    type: 'warning' | 'fine';
    status: 'issued' | 'acknowledged';
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
    attachments: Attachment[];
}

const money = (value: number | null) => value === null ? '-' : value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function Show({ record, types, statuses }: { record: RecordData; types: Record<string, string>; statuses: Record<string, string> }) {
    return (
        <PortalLayout title={types[record.type]}>
            <Head title={record.title} />
            <div className="space-y-4">
                <Link href={route('portal.disciplinary.index')} className="text-sm text-blue-600 hover:text-blue-700">Voltar</Link>

                <div className="rounded-xl border border-gray-100 bg-white p-5">
                    <div className="flex items-start gap-3">
                        <span className={`flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-lg ${record.type === 'fine' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-600'}`}>
                            <AlertTriangle className="h-5 w-5" />
                        </span>
                        <div className="min-w-0">
                            <h1 className="text-lg font-bold text-gray-900">{record.title}</h1>
                            <p className="text-sm text-gray-500">{record.condominium} - {record.unit}</p>
                        </div>
                    </div>

                    <dl className="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt className="text-gray-400">Status</dt><dd className="font-medium text-gray-900">{statuses[record.status]}</dd></div>
                        <div><dt className="text-gray-400">Regra</dt><dd className="font-medium text-gray-900">{record.rule_reference || '-'}</dd></div>
                        <div><dt className="text-gray-400">Data do fato</dt><dd className="font-medium text-gray-900">{record.occurred_on || '-'}</dd></div>
                        <div><dt className="text-gray-400">Valor</dt><dd className="font-medium text-gray-900">{money(record.amount)}</dd></div>
                        <div><dt className="text-gray-400">Vencimento</dt><dd className="font-medium text-gray-900">{record.due_date || '-'}</dd></div>
                        <div><dt className="text-gray-400">Ciencia</dt><dd className="font-medium text-gray-900">{record.acknowledged_at || '-'}</dd></div>
                    </dl>

                    <p className="mt-5 whitespace-pre-wrap text-sm leading-6 text-gray-700">{record.description}</p>

                    {record.status === 'issued' && (
                        <button onClick={() => router.post(route('portal.disciplinary.acknowledge', record.id), {}, { preserveScroll: true })} className="mt-5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            Registrar ciencia
                        </button>
                    )}
                </div>

                {record.attachments.length > 0 && (
                    <div className="rounded-xl border border-gray-100 bg-white p-5">
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
        </PortalLayout>
    );
}
