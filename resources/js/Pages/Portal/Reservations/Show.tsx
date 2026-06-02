import PortalLayout from '@/Layouts/PortalLayout';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CalendarRange, Clock, MapPin } from 'lucide-react';

interface Reservation {
    id: string; date: string; start_time: string; end_time: string; status: string;
    notes: string | null; decision_reason: string | null; decided_at: string | null;
    common_area: { name: string; rules: string | null } | null;
    condominium: { name: string } | null;
    decider: { name: string } | null;
}
interface Props {
    reservation: Reservation;
    statuses: Record<string, string>;
}

const statusStyles: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
    cancelled: 'bg-gray-100 text-gray-600',
};

export default function PortalReservationShow({ reservation, statuses }: Props) {
    const canCancel = ['pending', 'approved'].includes(reservation.status);

    const cancel = () => {
        const reason = window.prompt('Deseja informar um motivo para o cancelamento? (opcional)') ?? '';
        if (!window.confirm('Confirmar o cancelamento da reserva?')) return;
        router.post(route('portal.reservations.cancel', reservation.id), { reason });
    };

    return (
        <PortalLayout>
            <Head title="Reserva" />

            <Link href={route('portal.reservations.index')} className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <ArrowLeft className="h-4 w-4" /> Reservas
            </Link>

            <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <div className="flex items-start justify-between gap-3 border-b border-gray-100 p-5">
                    <div>
                        <h1 className="text-lg font-bold text-gray-900">{reservation.common_area?.name}</h1>
                        {reservation.condominium?.name && <p className="text-xs text-gray-500">{reservation.condominium.name}</p>}
                    </div>
                    <span className={`flex-shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold ${statusStyles[reservation.status] ?? 'bg-gray-100 text-gray-600'}`}>
                        {statuses[reservation.status] ?? reservation.status}
                    </span>
                </div>

                <div className="space-y-3 p-5 text-sm text-gray-700">
                    <p className="flex items-center gap-2"><CalendarRange className="h-4 w-4 text-gray-400" /> {new Date(reservation.date + 'T00:00:00').toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' })}</p>
                    <p className="flex items-center gap-2"><Clock className="h-4 w-4 text-gray-400" /> {reservation.start_time?.slice(0, 5)} às {reservation.end_time?.slice(0, 5)}</p>
                    {reservation.notes && <p className="flex items-start gap-2"><MapPin className="mt-0.5 h-4 w-4 text-gray-400" /> {reservation.notes}</p>}

                    {reservation.decision_reason && (
                        <div className="rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                            <span className="font-medium">Observação da administração:</span> {reservation.decision_reason}
                            {reservation.decider?.name && <span className="block text-gray-400">— {reservation.decider.name}</span>}
                        </div>
                    )}
                </div>

                {canCancel && (
                    <div className="border-t border-gray-100 p-5">
                        <button onClick={cancel} className="rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                            Cancelar reserva
                        </button>
                    </div>
                )}
            </div>
        </PortalLayout>
    );
}
