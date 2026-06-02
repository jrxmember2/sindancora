import PortalLayout from '@/Layouts/PortalLayout';
import { Head, Link } from '@inertiajs/react';
import { Wallet, ChevronRight, CheckCircle2 } from 'lucide-react';

interface Charge {
    id: string; description: string; type: string; reference_month: string | null;
    amount: string; current_amount: number; due_date: string; status: string;
    condominium: { name: string } | null; unit: { number: string } | null;
}
interface Props {
    open: Charge[];
    paid: Charge[];
    types: Record<string, string>;
    statuses: Record<string, string>;
}

const brl = (v: string | number) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const statusStyles: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700',
    overdue: 'bg-red-100 text-red-700',
    paid: 'bg-green-100 text-green-700',
};

function ChargeRow({ c, statuses }: { c: Charge; statuses: Record<string, string> }) {
    const overdue = c.status === 'overdue';
    return (
        <Link href={route('portal.charges.show', c.id)} className="flex items-center gap-3 px-4 py-3.5 transition-colors hover:bg-gray-50">
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-gray-900">{c.description}</p>
                <p className="text-xs text-gray-500">
                    {c.unit?.number ? `Unid. ${c.unit.number} · ` : ''}Vence {new Date(c.due_date + 'T00:00:00').toLocaleDateString('pt-BR')}
                </p>
            </div>
            <div className="text-right">
                <p className="text-sm font-semibold text-gray-900">{brl(overdue ? c.current_amount : c.amount)}</p>
                <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${statusStyles[c.status] ?? 'bg-gray-100 text-gray-600'}`}>{statuses[c.status] ?? c.status}</span>
            </div>
            <ChevronRight className="h-4 w-4 flex-shrink-0 text-gray-300" />
        </Link>
    );
}

export default function PortalCharges({ open, paid, statuses }: Props) {
    const totalOpen = open.reduce((s, c) => s + Number(c.status === 'overdue' ? c.current_amount : c.amount), 0);

    return (
        <PortalLayout title="Minhas cobranças">
            <Head title="Minhas cobranças" />

            {open.length > 0 && (
                <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p className="text-sm text-amber-800">Total em aberto</p>
                    <p className="text-2xl font-bold text-amber-900">{brl(totalOpen)}</p>
                </div>
            )}

            <section className="mb-6">
                <h2 className="mb-2 text-base font-semibold text-gray-900">Em aberto</h2>
                <div className="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    {open.length === 0 && (
                        <div className="px-4 py-8 text-center">
                            <CheckCircle2 className="mx-auto h-8 w-8 text-green-400" />
                            <p className="mt-2 text-sm text-gray-400">Você está em dia. 🎉</p>
                        </div>
                    )}
                    {open.map((c) => <ChargeRow key={c.id} c={c} statuses={statuses} />)}
                </div>
            </section>

            {paid.length > 0 && (
                <section>
                    <h2 className="mb-2 text-base font-semibold text-gray-900">Pagas</h2>
                    <div className="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                        {paid.map((c) => <ChargeRow key={c.id} c={c} statuses={statuses} />)}
                    </div>
                </section>
            )}

            {open.length === 0 && paid.length === 0 && (
                <div className="rounded-xl border border-gray-100 bg-white px-4 py-10 text-center shadow-sm">
                    <Wallet className="mx-auto h-8 w-8 text-gray-300" />
                    <p className="mt-2 text-sm text-gray-400">Nenhuma cobrança registrada.</p>
                </div>
            )}
        </PortalLayout>
    );
}
