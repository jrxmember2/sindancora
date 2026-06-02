import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Check, X, Ban, Building2, User, Calendar, Clock, CalendarRange } from 'lucide-react';

interface Named { id: string; name: string }
interface Area {
    id: string; name: string; requires_approval: boolean;
    opening_time: string | null; closing_time: string | null; fee: string | null; deposit: string | null; rules: string | null;
}
interface Reservation {
    id: string; date: string; start_time: string; end_time: string; status: string;
    notes: string | null; decision_reason: string | null; decided_at: string | null;
    common_area: Area | null; condominium: Named | null; requester: Named | null; decider: Named | null;
}
interface Props {
    reservation: Reservation;
    statuses: Record<string, string>;
    can: { approve: boolean; reject: boolean; cancel: boolean };
}

const statusStyle: Record<string, string> = {
    pending: 'bg-amber-50 text-amber-700', approved: 'bg-green-50 text-green-700',
    rejected: 'bg-red-50 text-red-700', cancelled: 'bg-gray-100 text-gray-500',
};
const hhmm = (t: string) => t.slice(0, 5);
const money = (v: string | null) => (v && Number(v) > 0 ? `R$ ${Number(v).toFixed(2).replace('.', ',')}` : null);
const fmtDate = (iso: string) => new Date(iso).toLocaleDateString('pt-BR');
const fmtDateTime = (iso: string | null) => (iso ? new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '');

export default function ReservationShow({ reservation: r, statuses, can }: Props) {
    const approve = () => { if (confirm('Aprovar esta reserva?')) router.post(route('reservations.approve', r.id), {}, { preserveScroll: true }); };
    const reject = () => {
        const reason = window.prompt('Motivo da recusa (opcional):') ?? undefined;
        if (reason !== undefined) router.post(route('reservations.reject', r.id), { reason }, { preserveScroll: true });
    };
    const cancel = () => {
        const reason = window.prompt('Motivo do cancelamento (opcional):') ?? undefined;
        if (reason !== undefined) router.post(route('reservations.cancel', r.id), { reason }, { preserveScroll: true });
    };

    const area = r.common_area;

    return (
        <AppLayout>
            <Head title={`Reserva — ${area?.name ?? ''}`} />
            <div className="mx-auto max-w-2xl space-y-4">
                <div className="flex items-center justify-between">
                    <Link href={route('reservations.index')} className="text-sm text-gray-500 hover:text-gray-700">← Reservas</Link>
                    <div className="flex gap-2">
                        {r.status === 'pending' && can.approve && (
                            <button onClick={approve} className="inline-flex items-center gap-2 rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-green-700">
                                <Check className="h-4 w-4" /> Aprovar
                            </button>
                        )}
                        {r.status === 'pending' && can.reject && (
                            <button onClick={reject} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-red-600 transition-colors hover:bg-red-50">
                                <X className="h-4 w-4" /> Recusar
                            </button>
                        )}
                        {(r.status === 'pending' || r.status === 'approved') && can.cancel && (
                            <button onClick={cancel} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                                <Ban className="h-4 w-4" /> Cancelar
                            </button>
                        )}
                    </div>
                </div>

                <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div className="mb-3 flex items-center gap-2">
                        <CalendarRange className="h-5 w-5 text-blue-600" />
                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusStyle[r.status] ?? ''}`}>{statuses[r.status] ?? r.status}</span>
                    </div>
                    <h1 className="text-2xl font-bold text-gray-900">{area?.name ?? 'Área comum'}</h1>

                    <div className="mt-3 flex flex-wrap gap-x-5 gap-y-1 border-b border-gray-100 pb-4 text-xs text-gray-500">
                        {r.condominium && <span className="inline-flex items-center gap-1"><Building2 className="h-3.5 w-3.5" /> {r.condominium.name}</span>}
                        <span className="inline-flex items-center gap-1"><Calendar className="h-3.5 w-3.5" /> {fmtDate(r.date)}</span>
                        <span className="inline-flex items-center gap-1"><Clock className="h-3.5 w-3.5" /> {hhmm(r.start_time)} às {hhmm(r.end_time)}</span>
                        {r.requester && <span className="inline-flex items-center gap-1"><User className="h-3.5 w-3.5" /> {r.requester.name}</span>}
                    </div>

                    {r.notes && (
                        <div className="mt-4">
                            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">Observações</p>
                            <p className="mt-1 whitespace-pre-wrap text-sm text-gray-700">{r.notes}</p>
                        </div>
                    )}

                    {(area?.fee || area?.deposit || area?.rules) && (
                        <div className="mt-4 rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                            {money(area.fee) && <p>Taxa de uso: {money(area.fee)}</p>}
                            {money(area.deposit) && <p>Caução: {money(area.deposit)}</p>}
                            {area.rules && <p className="mt-1 whitespace-pre-wrap">Regras: {area.rules}</p>}
                        </div>
                    )}

                    {(r.status === 'rejected' || r.status === 'cancelled') && (
                        <div className="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">
                            <p className="font-medium">{r.status === 'rejected' ? 'Recusada' : 'Cancelada'}{r.decider ? ` por ${r.decider.name}` : ''} {r.decided_at && `em ${fmtDateTime(r.decided_at)}`}</p>
                            {r.decision_reason && <p className="mt-0.5">Motivo: {r.decision_reason}</p>}
                        </div>
                    )}
                    {r.status === 'approved' && r.decided_at && (
                        <p className="mt-4 text-sm text-green-700">Aprovada{r.decider ? ` por ${r.decider.name}` : ''} em {fmtDateTime(r.decided_at)}.</p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
