import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { BarChart3, MessageCircle, Clock, Bot, UserCheck, Inbox } from 'lucide-react';

interface Bar { label: string; total: number }
interface Props {
    filters: { from: string; to: string };
    kpis: {
        total_conversations: number;
        open_now: number;
        closed_in_period: number;
        inbound: number;
        outbound: number;
        bot_messages: number;
        agent_messages: number;
        avg_first_response_minutes: number | null;
    };
    by_sector: Bar[];
    by_condominium: Bar[];
    attendants: Bar[];
}

function formatMinutes(min: number | null): string {
    if (min === null) return '—';
    if (min < 60) return `${min} min`;
    const h = Math.floor(min / 60);
    const m = Math.round(min % 60);
    return `${h}h${m ? ` ${m}min` : ''}`;
}

function BarList({ title, icon: Icon, data, color }: { title: string; icon: typeof BarChart3; data: Bar[]; color: string }) {
    const max = Math.max(1, ...data.map((d) => d.total));
    return (
        <div className="rounded-xl border border-gray-200 bg-white p-4">
            <h2 className="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-700"><Icon className="h-4 w-4" /> {title}</h2>
            {data.length === 0 ? (
                <p className="py-4 text-center text-sm text-gray-400">Sem dados no período.</p>
            ) : (
                <ul className="space-y-2">
                    {data.map((d) => (
                        <li key={d.label}>
                            <div className="mb-0.5 flex justify-between text-xs text-gray-600">
                                <span className="truncate">{d.label}</span>
                                <span className="font-medium">{d.total}</span>
                            </div>
                            <div className="h-2 w-full rounded-full bg-gray-100">
                                <div className={`h-2 rounded-full ${color}`} style={{ width: `${(d.total / max) * 100}%` }} />
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function Kpi({ icon: Icon, label, value, hint }: { icon: typeof BarChart3; label: string; value: string | number; hint?: string }) {
    return (
        <div className="rounded-xl border border-gray-200 bg-white p-4">
            <div className="flex items-center gap-2 text-gray-500"><Icon className="h-4 w-4" /><span className="text-xs">{label}</span></div>
            <p className="mt-1 text-2xl font-bold text-gray-900">{value}</p>
            {hint && <p className="text-xs text-gray-400">{hint}</p>}
        </div>
    );
}

export default function Reports({ filters, kpis, by_sector, by_condominium, attendants }: Props) {
    const apply = (patch: Partial<Props['filters']>) =>
        router.get(route('inbox.reports'), { ...filters, ...patch }, { preserveState: true, replace: true });

    return (
        <AppLayout>
            <Head title="Relatório de atendimento" />

            <div className="mx-auto max-w-5xl space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900">
                        <BarChart3 className="h-6 w-6 text-blue-600" /> Relatório de atendimento
                    </h1>
                    <div className="flex items-center gap-2 text-sm">
                        <input type="date" value={filters.from} onChange={(e) => apply({ from: e.target.value })} className="rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" />
                        <span className="text-gray-400">até</span>
                        <input type="date" value={filters.to} onChange={(e) => apply({ to: e.target.value })} className="rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" />
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <Kpi icon={MessageCircle} label="Conversas no período" value={kpis.total_conversations} />
                    <Kpi icon={Inbox} label="Abertas agora" value={kpis.open_now} hint={`${kpis.closed_in_period} encerradas no período`} />
                    <Kpi icon={Clock} label="Tempo de 1ª resposta" value={formatMinutes(kpis.avg_first_response_minutes)} hint="média" />
                    <Kpi icon={UserCheck} label="Mensagens (atendente)" value={kpis.agent_messages} hint={`${kpis.bot_messages} pelo chatbot`} />
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Kpi icon={MessageCircle} label="Mensagens recebidas" value={kpis.inbound} />
                    <Kpi icon={Bot} label="Mensagens enviadas" value={kpis.outbound} hint={`${kpis.agent_messages} atendente · ${kpis.bot_messages} bot`} />
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <BarList title="Conversas por setor" icon={BarChart3} data={by_sector} color="bg-blue-500" />
                    <BarList title="Conversas por condomínio" icon={BarChart3} data={by_condominium} color="bg-emerald-500" />
                </div>

                <BarList title="Ranking de atendentes (mensagens enviadas)" icon={UserCheck} data={attendants} color="bg-violet-500" />
            </div>
        </AppLayout>
    );
}
