import PortalLayout from '@/Layouts/PortalLayout';
import { Head, Link } from '@inertiajs/react';
import { Megaphone, AlertCircle, FileText, CalendarRange, Building2, ChevronRight, Plus } from 'lucide-react';

interface Unit { id: string; number: string; block: string | null; condominium: string | null; type: string }
interface Announcement { id: string; title: string; category: string; urgency: string; published_at: string }
interface Reservation { id: string; date: string; start_time: string; end_time: string; status: string; common_area: { name: string } | null }

interface Props {
    resident: { name: string; units: Unit[] };
    stats: { unread_announcements: number; open_occurrences: number; documents: number };
    recentAnnouncements: Announcement[];
    upcomingReservations: Reservation[];
    reservationStatuses: Record<string, string>;
    announcementCategories: Record<string, string>;
}

const statusStyles: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
    cancelled: 'bg-gray-100 text-gray-600',
};

export default function PortalDashboard({ resident, stats, recentAnnouncements, upcomingReservations, reservationStatuses, announcementCategories }: Props) {
    const firstName = resident.name.split(' ')[0];

    return (
        <PortalLayout>
            <Head title="Portal do Morador" />

            <div className="space-y-6">
                {/* Boas-vindas */}
                <div>
                    <h1 className="text-xl font-bold text-gray-900">Olá, {firstName}! 👋</h1>
                    {resident.units.length > 0 && (
                        <p className="mt-0.5 text-sm text-gray-500">
                            {resident.units.map((u) => `${u.condominium ?? ''} · ${u.block ? u.block + ' · ' : ''}${u.number}`).join('  |  ')}
                        </p>
                    )}
                </div>

                {/* Atalhos rápidos */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <Link href="/portal/comunicados" className="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm transition-colors hover:bg-gray-50">
                        <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600"><Megaphone className="h-5 w-5" /></span>
                        <div>
                            <p className="text-lg font-bold text-gray-900">{stats.unread_announcements}</p>
                            <p className="text-xs text-gray-500">Comunicados novos</p>
                        </div>
                    </Link>
                    <Link href="/portal/ocorrencias" className="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm transition-colors hover:bg-gray-50">
                        <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600"><AlertCircle className="h-5 w-5" /></span>
                        <div>
                            <p className="text-lg font-bold text-gray-900">{stats.open_occurrences}</p>
                            <p className="text-xs text-gray-500">Ocorrências abertas</p>
                        </div>
                    </Link>
                    <Link href="/portal/documentos" className="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm transition-colors hover:bg-gray-50">
                        <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-violet-50 text-violet-600"><FileText className="h-5 w-5" /></span>
                        <div>
                            <p className="text-lg font-bold text-gray-900">{stats.documents}</p>
                            <p className="text-xs text-gray-500">Documentos</p>
                        </div>
                    </Link>
                </div>

                {/* Comunicados recentes */}
                <section>
                    <div className="mb-2 flex items-center justify-between">
                        <h2 className="text-base font-semibold text-gray-900">Comunicados recentes</h2>
                        <Link href="/portal/comunicados" className="text-sm text-blue-600 hover:text-blue-700">Ver todos</Link>
                    </div>
                    <div className="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                        {recentAnnouncements.length === 0 && <p className="px-4 py-6 text-center text-sm text-gray-400">Nenhum comunicado por enquanto.</p>}
                        {recentAnnouncements.map((a) => (
                            <Link key={a.id} href={route('portal.announcements.show', a.id)} className="flex items-center justify-between gap-3 px-4 py-3 transition-colors hover:bg-gray-50">
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium text-gray-900">{a.title}</p>
                                    <p className="text-xs text-gray-500">{announcementCategories[a.category] ?? a.category} · {new Date(a.published_at).toLocaleDateString('pt-BR')}</p>
                                </div>
                                {a.urgency === 'high' && <span className="rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-semibold text-red-700">Urgente</span>}
                                <ChevronRight className="h-4 w-4 flex-shrink-0 text-gray-300" />
                            </Link>
                        ))}
                    </div>
                </section>

                {/* Próximas reservas */}
                <section>
                    <div className="mb-2 flex items-center justify-between">
                        <h2 className="text-base font-semibold text-gray-900">Minhas próximas reservas</h2>
                        <Link href={route('portal.reservations.create')} className="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-700"><Plus className="h-3.5 w-3.5" /> Reservar</Link>
                    </div>
                    <div className="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                        {upcomingReservations.length === 0 && (
                            <div className="px-4 py-6 text-center">
                                <CalendarRange className="mx-auto h-8 w-8 text-gray-300" />
                                <p className="mt-2 text-sm text-gray-400">Você não tem reservas futuras.</p>
                            </div>
                        )}
                        {upcomingReservations.map((r) => (
                            <Link key={r.id} href={route('portal.reservations.show', r.id)} className="flex items-center justify-between gap-3 px-4 py-3 transition-colors hover:bg-gray-50">
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium text-gray-900">{r.common_area?.name}</p>
                                    <p className="text-xs text-gray-500">{new Date(r.date + 'T00:00:00').toLocaleDateString('pt-BR')} · {r.start_time?.slice(0, 5)}–{r.end_time?.slice(0, 5)}</p>
                                </div>
                                <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${statusStyles[r.status] ?? 'bg-gray-100 text-gray-600'}`}>{reservationStatuses[r.status] ?? r.status}</span>
                            </Link>
                        ))}
                    </div>
                </section>

                {/* Minhas unidades */}
                {resident.units.length > 0 && (
                    <section>
                        <h2 className="mb-2 text-base font-semibold text-gray-900">Minha(s) unidade(s)</h2>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            {resident.units.map((u) => (
                                <div key={u.id} className="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                                    <Building2 className="h-5 w-5 text-blue-500" />
                                    <div>
                                        <p className="text-sm font-medium text-gray-900">{u.condominium} · {u.block ? u.block + ' · ' : ''}{u.number}</p>
                                        <p className="text-xs text-gray-500 capitalize">{u.type}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </PortalLayout>
    );
}
