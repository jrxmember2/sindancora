import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { BarChart3, AlertTriangle, Clock, Timer, ListChecks } from 'lucide-react';

interface Option { value: string; label: string }
interface Stats {
    byStatus: Record<string, number>;
    overdue: number;
    byPriority: Record<string, number>;
    byCategory: Record<string, number>;
    avgResolutionHours: number | null;
    avgFirstResponseHours: number | null;
    byAssignee: { name: string; total: number }[];
}
interface Props {
    stats: Stats;
    statuses: Record<string, string>;
    priorities: Record<string, string>;
    condominiums: Option[];
    filters: { condominium_id?: string };
    canConfigureSla: boolean;
}

function Card({ label, value, icon, accent }: { label: string; value: React.ReactNode; icon: React.ReactNode; accent?: string }) {
    return (
        <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <div className="flex items-center gap-2 text-gray-400">{icon}<span className="text-xs uppercase tracking-wide">{label}</span></div>
            <p className={`mt-1 text-2xl font-bold ${accent ?? 'text-gray-900'}`}>{value}</p>
        </div>
    );
}

function fmtHours(h: number | null) {
    if (h === null) return '—';
    if (h < 24) return `${h.toFixed(1)} h`;
    return `${(h / 24).toFixed(1)} d`;
}

function DistRow({ label, value, total }: { label: string; value: number; total: number }) {
    const pct = total > 0 ? Math.round((value / total) * 100) : 0;
    return (
        <div>
            <div className="flex items-center justify-between text-sm">
                <span className="text-gray-700">{label}</span>
                <span className="text-gray-500">{value}</span>
            </div>
            <div className="mt-1 h-1.5 w-full rounded-full bg-gray-100">
                <div className="h-1.5 rounded-full bg-blue-500" style={{ width: `${pct}%` }} />
            </div>
        </div>
    );
}

export default function OccurrencesDashboard({ stats, statuses, priorities, condominiums, filters, canConfigureSla }: Props) {
    const open = stats.byStatus.open ?? 0;
    const inProgress = stats.byStatus.in_progress ?? 0;
    const closed = stats.byStatus.closed ?? 0;

    const priorityTotal = Object.values(stats.byPriority).reduce((a, b) => a + b, 0);
    const categoryTotal = Object.values(stats.byCategory).reduce((a, b) => a + b, 0);

    const apply = (extra: Record<string, string>) =>
        router.get(route('occurrences.dashboard'), { condominium_id: filters.condominium_id ?? '', ...extra }, { preserveState: true, replace: true });

    return (
        <AppLayout>
            <Head title="Painel de chamados" />
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                        <BarChart3 className="h-6 w-6 text-blue-600" />
                        <h1 className="text-2xl font-bold text-gray-900">Painel de chamados</h1>
                    </div>
                    <div className="flex gap-2">
                        {condominiums.length > 1 && (
                            <select value={filters.condominium_id ?? ''} onChange={e => apply({ condominium_id: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                                <option value="">Todos os condomínios</option>
                                {condominiums.map(c => <option key={c.value} value={c.value}>{c.label}</option>)}
                            </select>
                        )}
                        <Link href={route('occurrences.index')} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">Lista</Link>
                        {canConfigureSla && (
                            <Link href={route('settings.occurrence-sla.edit')} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                                <Timer className="h-4 w-4" /> Configurar SLA
                            </Link>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card label="Abertas" value={open} icon={<ListChecks className="h-4 w-4" />} accent="text-amber-600" />
                    <Card label="Em andamento" value={inProgress} icon={<ListChecks className="h-4 w-4" />} accent="text-blue-600" />
                    <Card label="Atrasadas (SLA)" value={stats.overdue} icon={<AlertTriangle className="h-4 w-4" />} accent={stats.overdue > 0 ? 'text-red-600' : 'text-gray-900'} />
                    <Card label="Encerradas" value={closed} icon={<ListChecks className="h-4 w-4" />} accent="text-green-600" />
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Card label="Tempo médio de resolução" value={fmtHours(stats.avgResolutionHours)} icon={<Clock className="h-4 w-4" />} />
                    <Card label="Tempo médio de 1ª resposta" value={fmtHours(stats.avgFirstResponseHours)} icon={<Clock className="h-4 w-4" />} />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="space-y-3 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-700">Abertas por prioridade</h2>
                        {priorityTotal === 0 && <p className="text-sm text-gray-400">Sem chamados em aberto.</p>}
                        {Object.entries(priorities).map(([slug, label]) => (
                            (stats.byPriority[slug] ?? 0) > 0 && <DistRow key={slug} label={label} value={stats.byPriority[slug] ?? 0} total={priorityTotal} />
                        ))}
                    </div>

                    <div className="space-y-3 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-700">Abertas por categoria</h2>
                        {categoryTotal === 0 && <p className="text-sm text-gray-400">Sem chamados em aberto.</p>}
                        {Object.entries(stats.byCategory).map(([label, value]) => (
                            <DistRow key={label} label={label} value={value} total={categoryTotal} />
                        ))}
                    </div>

                    <div className="space-y-3 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-700">Carga por responsável</h2>
                        {stats.byAssignee.length === 0 && <p className="text-sm text-gray-400">Nenhum chamado atribuído.</p>}
                        {stats.byAssignee.map((a, i) => (
                            <div key={i} className="flex items-center justify-between text-sm">
                                <span className="text-gray-700">{a.name}</span>
                                <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{a.total}</span>
                            </div>
                        ))}
                    </div>
                </div>

                <p className="text-xs text-gray-400">Status (todas): {statuses.open} {open} · {statuses.in_progress} {inProgress} · {statuses.closed} {closed}.</p>
            </div>
        </AppLayout>
    );
}
