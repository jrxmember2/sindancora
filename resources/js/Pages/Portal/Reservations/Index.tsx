import PortalLayout from '@/Layouts/PortalLayout';
import { Head, Link, router } from '@inertiajs/react';
import { CalendarRange, Plus, ChevronLeft, ChevronRight } from 'lucide-react';

interface Reservation {
    id: string; date: string; start_time: string; end_time: string; status: string;
    common_area: { name: string } | null; condominium: { name: string } | null;
}
interface MonthReservation { id: string; date: string; start_time: string; end_time: string; status: string; common_area: { name: string } | null }
interface Props {
    reservations: { data: Reservation[] };
    monthReservations: MonthReservation[];
    month: string;
    areas: { value: string; label: string }[];
    statuses: Record<string, string>;
}

const statusStyles: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
    cancelled: 'bg-gray-100 text-gray-600',
};

function shiftMonth(month: string, delta: number): string {
    const [y, m] = month.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export default function PortalReservations({ reservations, monthReservations, month, statuses }: Props) {
    const monthLabel = new Date(month + '-01T00:00:00').toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
    const goMonth = (m: string) => router.get(route('portal.reservations.index'), { month: m }, { preserveScroll: true, preserveState: true });

    // Agrupa ocupação por dia.
    const byDay: Record<string, MonthReservation[]> = {};
    monthReservations.forEach((r) => { (byDay[r.date] ??= []).push(r); });
    const days = Object.keys(byDay).sort();

    return (
        <PortalLayout title="Reservas">
            <Head title="Reservas" />

            <div className="mb-4 flex justify-end">
                <Link href={route('portal.reservations.create')} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    <Plus className="h-4 w-4" /> Nova reserva
                </Link>
            </div>

            {/* Minhas reservas */}
            <section className="mb-6">
                <h2 className="mb-2 text-base font-semibold text-gray-900">Minhas reservas</h2>
                <div className="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    {reservations.data.length === 0 && (
                        <div className="px-4 py-10 text-center">
                            <CalendarRange className="mx-auto h-8 w-8 text-gray-300" />
                            <p className="mt-2 text-sm text-gray-400">Você ainda não fez reservas.</p>
                        </div>
                    )}
                    {reservations.data.map((r) => (
                        <Link key={r.id} href={route('portal.reservations.show', r.id)} className="flex items-center justify-between gap-3 px-4 py-3.5 transition-colors hover:bg-gray-50">
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium text-gray-900">{r.common_area?.name}</p>
                                <p className="text-xs text-gray-500">{new Date(r.date + 'T00:00:00').toLocaleDateString('pt-BR')} · {r.start_time?.slice(0, 5)}–{r.end_time?.slice(0, 5)}</p>
                            </div>
                            <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${statusStyles[r.status] ?? 'bg-gray-100 text-gray-600'}`}>{statuses[r.status] ?? r.status}</span>
                        </Link>
                    ))}
                </div>
            </section>

            {/* Ocupação do mês */}
            <section>
                <div className="mb-2 flex items-center justify-between">
                    <h2 className="text-base font-semibold text-gray-900">Ocupação das áreas</h2>
                    <div className="flex items-center gap-1">
                        <button onClick={() => goMonth(shiftMonth(month, -1))} className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100"><ChevronLeft className="h-4 w-4" /></button>
                        <span className="min-w-[120px] text-center text-sm font-medium capitalize text-gray-700">{monthLabel}</span>
                        <button onClick={() => goMonth(shiftMonth(month, 1))} className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100"><ChevronRight className="h-4 w-4" /></button>
                    </div>
                </div>
                <div className="space-y-2">
                    {days.length === 0 && <p className="rounded-xl border border-gray-100 bg-white px-4 py-6 text-center text-sm text-gray-400 shadow-sm">Nenhuma reserva neste mês — as áreas estão livres.</p>}
                    {days.map((day) => (
                        <div key={day} className="rounded-xl border border-gray-100 bg-white px-4 py-3 shadow-sm">
                            <p className="mb-1 text-xs font-semibold text-gray-500">{new Date(day + 'T00:00:00').toLocaleDateString('pt-BR', { weekday: 'short', day: '2-digit', month: '2-digit' })}</p>
                            <div className="space-y-1">
                                {byDay[day].map((r) => (
                                    <div key={r.id} className="flex items-center justify-between text-sm">
                                        <span className="text-gray-700">{r.common_area?.name} · {r.start_time?.slice(0, 5)}–{r.end_time?.slice(0, 5)}</span>
                                        <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${statusStyles[r.status] ?? 'bg-gray-100 text-gray-600'}`}>{statuses[r.status] ?? r.status}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </section>
        </PortalLayout>
    );
}
