import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarRange, Plus, ChevronLeft, ChevronRight, LayoutGrid, Eye } from 'lucide-react';
import type { PageProps } from '@/types';

interface Option { value: string; label: string }
interface ListReservation {
    id: string; date: string; start_time: string; end_time: string; status: string;
    common_area: { id: string; name: string } | null;
    condominium: { id: string; name: string } | null;
    requester: { id: string; name: string } | null;
}
interface CalReservation { id: string; common_area_id: string; common_area: string | null; date: string; start_time: string; end_time: string; status: string }
interface Calendar { view: 'month' | 'week'; month: string; week_start: string; reservations: CalReservation[] }
interface Props {
    reservations: { data: ListReservation[] };
    calendar: Calendar;
    areas: Option[];
    statuses: Record<string, string>;
    filters: { status?: string; common_area_id?: string };
}

const pad = (n: number) => String(n).padStart(2, '0');
const toYmd = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

const statusStyle: Record<string, string> = {
    pending: 'bg-amber-50 text-amber-700',
    approved: 'bg-green-50 text-green-700',
    rejected: 'bg-red-50 text-red-700',
    cancelled: 'bg-gray-100 text-gray-500',
};
const dotStyle: Record<string, string> = {
    pending: 'bg-amber-400',
    approved: 'bg-green-500',
};
const hhmm = (t: string) => t.slice(0, 5);
const WEEKDAYS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
const MONTHS = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

export default function ReservationsIndex({ reservations, calendar, areas, statuses, filters }: Props) {
    const { auth } = usePage<PageProps>().props;
    const perms = auth.user?.permissions ?? [];
    const can = (p: string) => perms.includes('*') || perms.includes(p);

    const { view, month, week_start } = calendar;

    const navigate = (params: Record<string, string>) =>
        router.get(route('reservations.index'), {
            view, month, week: week_start, status: filters.status ?? '', common_area_id: filters.common_area_id ?? '', ...params,
        }, { preserveState: true, replace: true });

    const [yy, mm] = month.split('-').map(Number);
    const shiftMonth = (delta: number) => {
        const d = new Date(yy, mm - 1 + delta, 1);
        navigate({ month: `${d.getFullYear()}-${pad(d.getMonth() + 1)}` });
    };
    const shiftWeek = (delta: number) => {
        const d = new Date(`${week_start}T00:00:00`);
        d.setDate(d.getDate() + delta * 7);
        navigate({ week: toYmd(d) });
    };

    // Agrupa as reservas por data (YYYY-MM-DD).
    const byDate: Record<string, CalReservation[]> = {};
    calendar.reservations.forEach(r => { (byDate[r.date] ??= []).push(r); });

    // Células do mês (semana começando no domingo).
    const firstWeekday = new Date(yy, mm - 1, 1).getDay();
    const daysInMonth = new Date(yy, mm, 0).getDate();
    const monthCells: (number | null)[] = [
        ...Array(firstWeekday).fill(null),
        ...Array.from({ length: daysInMonth }, (_, i) => i + 1),
    ];

    // Dias da semana (a partir de week_start, domingo).
    const weekDays: Date[] = Array.from({ length: 7 }, (_, i) => {
        const d = new Date(`${week_start}T00:00:00`);
        d.setDate(d.getDate() + i);
        return d;
    });
    const weekEnd = weekDays[6];
    const todayYmd = toYmd(new Date());

    const viewBtn = (v: 'month' | 'week', label: string) => (
        <button
            onClick={() => navigate({ view: v })}
            className={`rounded-lg px-3 py-1.5 text-sm font-medium transition-colors ${view === v ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'}`}
        >
            {label}
        </button>
    );

    return (
        <AppLayout>
            <Head title="Reservas" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <CalendarRange className="h-6 w-6 text-blue-600" />
                        <h1 className="text-2xl font-bold text-gray-900">Reservas</h1>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('areas.index')} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                            <LayoutGrid className="h-4 w-4" /> Áreas Comuns
                        </Link>
                        {can('reservations:create') && (
                            <Link href={route('reservations.create')} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700">
                                <Plus className="h-4 w-4" /> Nova Reserva
                            </Link>
                        )}
                    </div>
                </div>

                {/* Calendário */}
                <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                            {view === 'month' ? (
                                <>
                                    <button onClick={() => shiftMonth(-1)} className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"><ChevronLeft className="h-4 w-4" /></button>
                                    <span className="min-w-[150px] text-center text-sm font-semibold text-gray-900">{MONTHS[mm - 1]} de {yy}</span>
                                    <button onClick={() => shiftMonth(1)} className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"><ChevronRight className="h-4 w-4" /></button>
                                </>
                            ) : (
                                <>
                                    <button onClick={() => shiftWeek(-1)} className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"><ChevronLeft className="h-4 w-4" /></button>
                                    <span className="min-w-[150px] text-center text-sm font-semibold text-gray-900">
                                        {weekDays[0].getDate()} {MONTHS[weekDays[0].getMonth()].slice(0, 3)} – {weekEnd.getDate()} {MONTHS[weekEnd.getMonth()].slice(0, 3)}
                                    </span>
                                    <button onClick={() => shiftWeek(1)} className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"><ChevronRight className="h-4 w-4" /></button>
                                </>
                            )}
                        </div>
                        <div className="flex items-center gap-2">
                            <div className="flex rounded-lg border border-gray-200 p-0.5">
                                {viewBtn('month', 'Mês')}
                                {viewBtn('week', 'Semana')}
                            </div>
                            <select value={filters.common_area_id ?? ''} onChange={e => navigate({ common_area_id: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                                <option value="">Todas as áreas</option>
                                {areas.map(a => <option key={a.value} value={a.value}>{a.label}</option>)}
                            </select>
                        </div>
                    </div>

                    {view === 'month' ? (
                        <div className="grid grid-cols-7 gap-px overflow-hidden rounded-lg bg-gray-100 text-xs">
                            {WEEKDAYS.map(d => <div key={d} className="bg-gray-50 py-1.5 text-center font-medium text-gray-500">{d}</div>)}
                            {monthCells.map((day, i) => {
                                const dayRes = day ? (byDate[`${month}-${pad(day)}`] ?? []) : [];
                                return (
                                    <div key={i} className="min-h-[72px] bg-white p-1">
                                        {day && (
                                            <>
                                                <span className="text-[11px] font-medium text-gray-400">{day}</span>
                                                <div className="mt-0.5 space-y-0.5">
                                                    {dayRes.slice(0, 3).map(r => (
                                                        <div key={r.id} className="flex items-center gap-1 truncate" title={`${hhmm(r.start_time)}–${hhmm(r.end_time)} · ${r.common_area ?? ''}`}>
                                                            <span className={`h-1.5 w-1.5 flex-shrink-0 rounded-full ${dotStyle[r.status] ?? 'bg-gray-300'}`} />
                                                            <span className="truncate text-[10px] text-gray-600">{hhmm(r.start_time)}</span>
                                                        </div>
                                                    ))}
                                                    {dayRes.length > 3 && <span className="text-[10px] text-gray-400">+{dayRes.length - 3}</span>}
                                                </div>
                                            </>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="grid grid-cols-7 gap-px overflow-hidden rounded-lg bg-gray-100 text-xs">
                            {weekDays.map((d, i) => {
                                const ymd = toYmd(d);
                                const dayRes = byDate[ymd] ?? [];
                                const isToday = ymd === todayYmd;
                                return (
                                    <div key={i} className="min-h-[160px] bg-white p-1.5">
                                        <div className={`mb-1 text-center ${isToday ? 'text-blue-600' : 'text-gray-500'}`}>
                                            <div className="text-[10px] font-medium uppercase">{WEEKDAYS[i]}</div>
                                            <div className={`text-sm font-semibold ${isToday ? 'flex h-6 w-6 mx-auto items-center justify-center rounded-full bg-blue-600 text-white' : ''}`}>{d.getDate()}</div>
                                        </div>
                                        <div className="space-y-1">
                                            {dayRes.map(r => (
                                                <div key={r.id} className={`rounded px-1 py-0.5 text-[10px] leading-tight ${r.status === 'approved' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'}`} title={r.common_area ?? ''}>
                                                    <div className="font-medium">{hhmm(r.start_time)}–{hhmm(r.end_time)}</div>
                                                    <div className="truncate">{r.common_area}</div>
                                                </div>
                                            ))}
                                            {dayRes.length === 0 && <div className="text-center text-[10px] text-gray-300">—</div>}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    <div className="mt-2 flex gap-4 text-[11px] text-gray-500">
                        <span className="flex items-center gap-1"><span className="h-2 w-2 rounded-full bg-green-500" /> Aprovada</span>
                        <span className="flex items-center gap-1"><span className="h-2 w-2 rounded-full bg-amber-400" /> Pendente</span>
                    </div>
                </div>

                {/* Filtro de status + lista */}
                <select value={filters.status ?? ''} onChange={e => navigate({ status: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option value="">Todos os status</option>
                    {Object.entries(statuses).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>

                <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b border-gray-100 bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Área</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Data</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Horário</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Solicitante</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {reservations.data.length === 0 && (
                                <tr><td colSpan={6} className="px-4 py-8 text-center text-sm text-gray-500">Nenhuma reserva encontrada.</td></tr>
                            )}
                            {reservations.data.map(r => (
                                <tr key={r.id} className="transition-colors hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium text-gray-900">{r.common_area?.name ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-600">{new Date(r.date).toLocaleDateString('pt-BR')}</td>
                                    <td className="px-4 py-3 text-gray-600">{hhmm(r.start_time)}–{hhmm(r.end_time)}</td>
                                    <td className="px-4 py-3 text-xs text-gray-600">{r.requester?.name ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusStyle[r.status] ?? ''}`}>{statuses[r.status] ?? r.status}</span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end">
                                            <Link href={route('reservations.show', r.id)} className="rounded p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"><Eye className="h-4 w-4" /></Link>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
