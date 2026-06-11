import PortalLayout from '@/Layouts/PortalLayout';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';

interface RecordRow {
    id: string;
    type: 'warning' | 'fine';
    status: 'issued' | 'acknowledged';
    title: string;
    rule_reference: string | null;
    condominium: string | null;
    unit: string | null;
    issued_at: string | null;
    amount: number | null;
    due_date: string | null;
}

const badge: Record<string, string> = {
    issued: 'bg-amber-50 text-amber-700',
    acknowledged: 'bg-blue-50 text-blue-700',
};

const money = (value: number | null) => value === null ? '-' : value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function Index({ records, types, statuses }: { records: RecordRow[]; types: Record<string, string>; statuses: Record<string, string> }) {
    return (
        <PortalLayout title="Multas e advertencias">
            <Head title="Multas e advertencias" />
            <div className="space-y-2">
                {records.length === 0 && <p className="rounded-xl border border-gray-100 bg-white py-10 text-center text-sm text-gray-400">Nenhum registro regimental.</p>}
                {records.map((record) => (
                    <Link key={record.id} href={route('portal.disciplinary.show', record.id)} className="flex gap-3 rounded-xl border border-gray-100 bg-white p-4">
                        <span className={`flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-lg ${record.type === 'fine' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-600'}`}>
                            <AlertTriangle className="h-5 w-5" />
                        </span>
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-700">{types[record.type]}</span>
                                <span className={`rounded-full px-2 py-0.5 text-[11px] font-medium ${badge[record.status]}`}>{statuses[record.status]}</span>
                            </div>
                            <p className="mt-1 font-medium text-gray-900">{record.title}</p>
                            <p className="text-xs text-gray-500">{record.condominium} - {record.unit}</p>
                            {record.type === 'fine' && <p className="mt-1 text-xs text-gray-500">{money(record.amount)} - venc. {record.due_date ?? '-'}</p>}
                        </div>
                    </Link>
                ))}
            </div>
        </PortalLayout>
    );
}
