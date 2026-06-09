import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    CalendarDays,
    CalendarRange,
    ChevronLeft,
    ChevronRight,
    CircleAlert,
    Clock,
    Hammer,
    Receipt,
    Vote,
    Wallet,
    Wrench,
} from 'lucide-react';
import type { ElementType } from 'react';

interface Option { value: string; label: string }

interface ScheduleEvent {
    id: string;
    record_id: string;
    source: string;
    source_label: string;
    title: string;
    description: string | null;
    date: string;
    time: string | null;
    end_time: string | null;
    status: string | null;
    status_label: string | null;
    condominium: { id: string; name: string } | null;
    url: string | null;
    amount: number | null;
    is_overdue: boolean;
}

interface Props {
    calendar: { month: string; from: string; to: string };
    events: ScheduleEvent[];
    summary: {
        total: number;
        today: number;
        next_7_days: number;
        overdue: number;
        by_source: Record<string, number>;
    };
    condominiums: Option[];
    sources: Record<string, string>;
    filters: { condominium_id?: string | null; source?: string | null };
}

const WEEKDAYS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
const MONTHS = ['Janeiro', 'Fevereiro', 'Marco', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

const sourceStyles: Record<string, { dot: string; badge: string; bar: string }> = {
    reservations: { dot: 'bg-emerald-500', badge: 'bg-emerald-50 text-emerald-700', bar: 'border-l-emerald-500' },
    assemblies: { dot: 'bg-violet-500', badge: 'bg-violet-50 text-violet-700', bar: 'border-l-violet-500' },
    maintenance: { dot: 'bg-amber-500', badge: 'bg-amber-50 text-amber-700', bar: 'border-l-amber-500' },
    works: { dot: 'bg-sky-500', badge: 'bg-sky-50 text-sky-700', bar: 'border-l-sky-500' },
    occurrences: { dot: 'bg-red-500', badge: 'bg-red-50 text-red-700', bar: 'border-l-red-500' },
    expenses: { dot: 'bg-rose-500', badge: 'bg-rose-50 text-rose-700', bar: 'border-l-rose-500' },
    charges: { dot: 'bg-blue-500', badge: 'bg-blue-50 text-blue-700', bar: 'border-l-blue-500' },
};

const sourceIcons: Record<string, ElementType> = {
    reservations: CalendarRange,
    assemblies: Vote,
    maintenance: Wrench,
    works: Hammer,
    occurrences: AlertCircle,
    expenses: Receipt,
    charges: Wallet,
};

const pad = (n: number) => String(n).padStart(2, '0');
const todayYmd = () => {
    const date = new Date();
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
};
const brl = (value: number | null) => value === null ? null : value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const dateLabel = (value: string) => new Date(`${value}T00:00:00`).toLocaleDateString('pt-BR', { weekday: 'short', day: '2-digit', month: '2-digit' });

function shiftMonth(month: string, delta: number) {
    const [year, monthNumber] = month.split('-').map(Number);
    const date = new Date(year, monthNumber - 1 + delta, 1);

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;
}

function SummaryCard({ label, value, icon: Icon, tone }: { label: string; value: number; icon: ElementType; tone: string }) {
    return (
        <div className="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-gray-500">{label}</p>
                    <p className="mt-1 text-xl font-bold text-gray-900">{value}</p>
                </div>
                <div className={`rounded-lg p-2 text-white ${tone}`}>
                    <Icon className="h-5 w-5" />
                </div>
            </div>
        </div>
    );
}

function EventRow({ event, compact = false }: { event: ScheduleEvent; compact?: boolean }) {
    const style = sourceStyles[event.source] ?? sourceStyles.reservations;
    const Icon = sourceIcons[event.source] ?? CalendarDays;
    const amount = brl(event.amount);
    const time = event.time ? (event.end_time ? `${event.time}-${event.end_time}` : event.time) : 'Dia todo';
    const content = (
        <div className={`border-l-4 ${event.is_overdue ? 'border-l-red-500 bg-red-50/50' : style.bar} ${compact ? 'rounded px-2 py-1' : 'rounded-lg border border-gray-100 bg-white p-3 shadow-sm'}`}>
            <div className="flex min-w-0 items-start justify-between gap-2">
                <div className="min-w-0">
                    <div className="flex items-center gap-1.5">
                        <Icon className={`h-3.5 w-3.5 flex-shrink-0 ${event.is_overdue ? 'text-red-600' : 'text-gray-400'}`} />
                        <span className="truncate text-sm font-semibold text-gray-900">{event.title}</span>
                    </div>
                    {!compact && (
                        <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                            <span>{time}</span>
                            {event.condominium?.name && <span>{event.condominium.name}</span>}
                            {amount && <span className="font-medium text-gray-700">{amount}</span>}
                        </div>
                    )}
                    {!compact && event.description && <p className="mt-1 text-xs text-gray-500">{event.description}</p>}
                </div>
                {!compact && event.status_label && (
                    <span className={`flex-shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium ${event.is_overdue ? 'bg-red-100 text-red-700' : style.badge}`}>
                        {event.status_label}
                    </span>
                )}
            </div>
        </div>
    );

    if (!event.url) {
        return content;
    }

    return (
        <Link href={event.url} className="block transition-opacity hover:opacity-80">
            {content}
        </Link>
    );
}

export default function ScheduleIndex({ calendar, events, summary, condominiums, sources, filters }: Props) {
    const [year, monthNumber] = calendar.month.split('-').map(Number);
    const firstWeekday = new Date(year, monthNumber - 1, 1).getDay();
    const daysInMonth = new Date(year, monthNumber, 0).getDate();
    const cells: (number | null)[] = [
        ...Array(firstWeekday).fill(null),
        ...Array.from({ length: daysInMonth }, (_, index) => index + 1),
    ];
    while (cells.length % 7 !== 0) {
        cells.push(null);
    }

    const byDate = events.reduce<Record<string, ScheduleEvent[]>>((acc, event) => {
        (acc[event.date] ??= []).push(event);
        return acc;
    }, {});
    const grouped = Object.entries(byDate).sort(([a], [b]) => a.localeCompare(b));
    const today = todayYmd();

    const navigate = (params: Record<string, string>) => router.get(
        route('schedule.index'),
        {
            month: calendar.month,
            condominium_id: filters.condominium_id ?? '',
            source: filters.source ?? '',
            ...params,
        },
        { preserveState: true, replace: true },
    );

    return (
        <AppLayout>
            <Head title="Cronograma" />

            <div className="space-y-6">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex items-center gap-2">
                        <CalendarDays className="h-6 w-6 text-blue-600" />
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Cronograma</h1>
                            <p className="text-sm text-gray-500">{MONTHS[monthNumber - 1]} de {year}</p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <button onClick={() => navigate({ month: shiftMonth(calendar.month, -1) })} className="rounded-lg border border-gray-200 p-2 text-gray-500 hover:bg-gray-50">
                            <ChevronLeft className="h-4 w-4" />
                        </button>
                        <button onClick={() => navigate({ month: todayYmd().slice(0, 7) })} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <Clock className="h-4 w-4" /> Hoje
                        </button>
                        <button onClick={() => navigate({ month: shiftMonth(calendar.month, 1) })} className="rounded-lg border border-gray-200 p-2 text-gray-500 hover:bg-gray-50">
                            <ChevronRight className="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard label="Eventos" value={summary.total} icon={CalendarDays} tone="bg-blue-600" />
                    <SummaryCard label="Hoje" value={summary.today} icon={Clock} tone="bg-emerald-600" />
                    <SummaryCard label="7 dias" value={summary.next_7_days} icon={CalendarRange} tone="bg-amber-500" />
                    <SummaryCard label="Atrasados" value={summary.overdue} icon={CircleAlert} tone="bg-red-600" />
                </div>

                <div className="flex flex-col gap-2 rounded-lg border border-gray-100 bg-white p-3 shadow-sm lg:flex-row lg:flex-wrap">
                    <select value={filters.condominium_id ?? ''} onChange={(e) => navigate({ condominium_id: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">Todos os condominios</option>
                        {condominiums.map((condominium) => <option key={condominium.value} value={condominium.value}>{condominium.label}</option>)}
                    </select>
                    <select value={filters.source ?? ''} onChange={(e) => navigate({ source: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">Todas as fontes</option>
                        {Object.entries(sources).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                    <button onClick={() => router.get(route('schedule.index'), {}, { replace: true })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                        Limpar
                    </button>
                </div>

                {Object.keys(sources).length > 0 && (
                    <div className="flex flex-wrap gap-2 text-xs text-gray-600">
                        {Object.entries(sources).map(([source, label]) => {
                            const style = sourceStyles[source] ?? sourceStyles.reservations;
                            return (
                                <span key={source} className="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white px-2.5 py-1">
                                    <span className={`h-2 w-2 rounded-full ${style.dot}`} />
                                    {label}
                                    <span className="text-gray-400">{summary.by_source[source] ?? 0}</span>
                                </span>
                            );
                        })}
                    </div>
                )}

                <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                    <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                        <div className="grid grid-cols-7 border-b border-gray-100 bg-gray-50 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">
                            {WEEKDAYS.map((day) => <div key={day} className="px-2 py-2">{day}</div>)}
                        </div>
                        <div className="grid grid-cols-7 bg-gray-100 gap-px">
                            {cells.map((day, index) => {
                                const ymd = day ? `${calendar.month}-${pad(day)}` : null;
                                const dayEvents = ymd ? (byDate[ymd] ?? []) : [];
                                const isToday = ymd === today;

                                return (
                                    <div key={index} className="min-h-[118px] bg-white p-2">
                                        {day && (
                                            <>
                                                <div className={`mb-1 flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold ${isToday ? 'bg-blue-600 text-white' : 'text-gray-500'}`}>
                                                    {day}
                                                </div>
                                                <div className="space-y-1">
                                                    {dayEvents.slice(0, 3).map((event) => (
                                                        <EventRow key={event.id} event={event} compact />
                                                    ))}
                                                    {dayEvents.length > 3 && (
                                                        <span className="block rounded bg-gray-50 px-2 py-1 text-[11px] text-gray-500">
                                                            +{dayEvents.length - 3} evento(s)
                                                        </span>
                                                    )}
                                                </div>
                                            </>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-500">Agenda do mes</h2>
                            <span className="text-xs text-gray-400">{events.length} evento(s)</span>
                        </div>
                        {events.length === 0 && (
                            <div className="rounded-xl border border-gray-100 bg-white px-4 py-10 text-center text-sm text-gray-400 shadow-sm">
                                Nenhum evento no periodo.
                            </div>
                        )}
                        {grouped.map(([date, dayEvents]) => (
                            <div key={date} className="space-y-2">
                                <div className={`sticky top-16 z-10 rounded-lg border px-3 py-1.5 text-xs font-semibold ${date === today ? 'border-blue-100 bg-blue-50 text-blue-700' : 'border-gray-100 bg-gray-50 text-gray-500'}`}>
                                    {dateLabel(date)}
                                </div>
                                {dayEvents.map((event) => <EventRow key={event.id} event={event} />)}
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
