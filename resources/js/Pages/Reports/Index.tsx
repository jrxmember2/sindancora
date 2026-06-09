import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    BarChart3,
    Building2,
    FileDown,
    FileSpreadsheet,
    FileText,
    Filter,
    Hammer,
    RefreshCcw,
    TrendingUp,
    Wallet,
    Wrench,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ElementType } from 'react';

interface Option {
    value: string;
    label: string;
}

interface CondominiumInfo {
    id: string;
    name: string;
    city: string | null;
    state: string | null;
}

interface StructureStats {
    units: number;
    occupied_units: number;
    vacant_units: number;
    renovation_units: number;
    residents: number;
}

interface FinancialStats {
    charged: number;
    charged_count: number;
    received: number;
    expenses_paid: number;
    balance: number;
    open_charges: number;
    open_charges_count: number;
    overdue_charges: number;
    delinquent_units: number;
    open_expenses: number;
    open_expenses_count: number;
}

interface OccurrenceStats {
    created: number;
    open: number;
    closed: number;
    sla_overdue: number;
    high_priority: number;
    avg_resolution_hours: number;
}

interface ReservationStats {
    total: number;
    pending: number;
    approved: number;
    rejected: number;
    cancelled: number;
}

interface MaintenanceStats {
    active: number;
    overdue: number;
    due_soon: number;
    executions: number;
    execution_cost: number;
}

interface WorkStats {
    created: number;
    active: number;
    overdue: number;
    completed: number;
    budget_amount: number;
    final_amount: number;
    variance: number;
}

interface DocumentStats {
    uploaded: number;
    current: number;
    expiring: number;
    expired: number;
}

interface QuotationStats {
    created: number;
    collecting: number;
    approved: number;
    approved_amount: number;
}

interface RiskStats {
    score: number;
    level: 'Alto' | 'Medio' | 'Baixo' | string;
}

interface ReportRow {
    condominium: CondominiumInfo;
    structure: StructureStats;
    financial: FinancialStats;
    occurrences: OccurrenceStats;
    reservations: ReservationStats;
    maintenance: MaintenanceStats;
    works: WorkStats;
    documents: DocumentStats;
    quotations: QuotationStats;
    risk: RiskStats;
}

interface MonthlyRow {
    month: string;
    label: string;
    charged: number;
    received: number;
    expenses: number;
    occurrences: number;
    reservations: number;
    maintenance_executions: number;
    works_completed: number;
}

interface RankingItem {
    condominium: CondominiumInfo;
    value: number;
    detail: string;
}

interface Report {
    summary: {
        condominiums: number;
        units: number;
        occupied_units: number;
        residents: number;
        active_modules: string[];
        financial: {
            charged: number;
            received: number;
            expenses_paid: number;
            balance: number;
            open_charges: number;
            overdue_charges: number;
            delinquent_units: number;
            open_expenses: number;
        };
        operations: {
            open_occurrences: number;
            sla_overdue: number;
            pending_reservations: number;
            maintenance_overdue: number;
            maintenance_due_soon: number;
            active_works: number;
            works_overdue: number;
            documents_expiring: number;
            documents_expired: number;
            quotations_collecting: number;
        };
        risk: { high: number; medium: number; low: number };
    };
    by_condominium: ReportRow[];
    monthly: MonthlyRow[];
    rankings: {
        financial_risk: RankingItem[];
        operational_risk: RankingItem[];
        expenses: RankingItem[];
    };
    available_modules: Option[];
    available_condominiums: Option[];
    filters: {
        from: string;
        to: string;
        condominium_ids: string[];
        modules: string[];
    };
}

interface Props {
    report: Report;
    canExport: boolean;
}

const brl = (value: number) => Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const number = (value: number) => Number(value ?? 0).toLocaleString('pt-BR');
const percent = (value: number, total: number) => total > 0 ? `${Math.round((value / total) * 100)}%` : '0%';

const moduleTone: Record<string, string> = {
    financial: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    occurrences: 'border-red-200 bg-red-50 text-red-700',
    reservations: 'border-sky-200 bg-sky-50 text-sky-700',
    maintenance: 'border-amber-200 bg-amber-50 text-amber-700',
    works: 'border-indigo-200 bg-indigo-50 text-indigo-700',
    documents: 'border-violet-200 bg-violet-50 text-violet-700',
    quotations: 'border-cyan-200 bg-cyan-50 text-cyan-700',
};

function toggle(list: string[], value: string): string[] {
    return list.includes(value) ? list.filter((item) => item !== value) : [...list, value];
}

function StatCard({ label, value, detail, icon: Icon, tone }: { label: string; value: string; detail?: string; icon: ElementType; tone: string }) {
    return (
        <div className="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="truncate text-xs font-medium uppercase tracking-wide text-gray-500">{label}</p>
                    <p className="mt-1 truncate text-xl font-bold text-gray-900">{value}</p>
                    {detail && <p className="mt-1 truncate text-xs text-gray-500">{detail}</p>}
                </div>
                <div className={`rounded-lg p-2 text-white ${tone}`}>
                    <Icon className="h-5 w-5" />
                </div>
            </div>
        </div>
    );
}

function RiskBadge({ risk }: { risk: RiskStats }) {
    const className = risk.level === 'Alto'
        ? 'bg-red-50 text-red-700 ring-red-100'
        : risk.level === 'Medio'
            ? 'bg-amber-50 text-amber-700 ring-amber-100'
            : 'bg-emerald-50 text-emerald-700 ring-emerald-100';

    return (
        <span className={`inline-flex min-w-[76px] items-center justify-center rounded-full px-2 py-1 text-xs font-semibold ring-1 ${className}`}>
            {risk.level} {risk.score}
        </span>
    );
}

function RankingList({ title, items, money = false }: { title: string; items: RankingItem[]; money?: boolean }) {
    return (
        <div className="rounded-lg border border-gray-100 bg-white shadow-sm">
            <div className="border-b border-gray-100 px-4 py-3">
                <h2 className="text-sm font-semibold text-gray-900">{title}</h2>
            </div>
            <div className="divide-y divide-gray-50">
                {items.length === 0 && <p className="px-4 py-8 text-center text-sm text-gray-400">Sem dados no periodo.</p>}
                {items.map((item) => (
                    <div key={item.condominium.id} className="flex items-center justify-between gap-3 px-4 py-3">
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium text-gray-900">{item.condominium.name}</p>
                            <p className="truncate text-xs text-gray-500">{item.detail}</p>
                        </div>
                        <span className="flex-shrink-0 text-sm font-semibold text-gray-900">
                            {money ? brl(item.value) : number(item.value)}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function ConsolidatedReports({ report, canExport }: Props) {
    const [from, setFrom] = useState(report.filters.from);
    const [to, setTo] = useState(report.filters.to);
    const [condominiumIds, setCondominiumIds] = useState<string[]>(report.filters.condominium_ids);
    const [modules, setModules] = useState<string[]>(report.filters.modules);

    const allCondominiumIds = report.available_condominiums.map((condominium) => condominium.value);
    const selectedModules = modules.length > 0 ? modules : report.available_modules.map((module) => module.value);
    const selectedCondominiums = condominiumIds.length > 0 ? condominiumIds : allCondominiumIds;
    const exportQuery = useMemo(() => {
        const params = new URLSearchParams({ from, to });
        if (condominiumIds.length === 1) {
            params.set('condominium_id', condominiumIds[0]);
        }
        return params.toString();
    }, [from, to, condominiumIds]);

    const maxMonthlyValue = Math.max(
        1,
        ...report.monthly.map((month) => Math.max(month.charged, month.received, month.expenses)),
    );

    const apply = () => router.get(
        route('reports.index'),
        {
            from,
            to,
            condominium_ids: condominiumIds,
            modules,
        },
        { preserveState: true, replace: true },
    );

    const reset = () => router.get(route('reports.index'), {}, { replace: true });

    const canUseFinancialExport = canExport && condominiumIds.length <= 1 && selectedModules.includes('financial');
    const summary = report.summary;

    return (
        <AppLayout>
            <Head title="Relatorios consolidados" />

            <div className="space-y-6">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex items-center gap-2">
                        <BarChart3 className="h-6 w-6 text-blue-600" />
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Relatorios consolidados</h1>
                            <p className="text-sm text-gray-500">{summary.condominiums} condominio(s) no escopo</p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {canUseFinancialExport && (
                            <>
                                <a href={`${route('reports.pdf')}?${exportQuery}`} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    <FileDown className="h-4 w-4" /> PDF financeiro
                                </a>
                                <a href={`${route('reports.xlsx')}?${exportQuery}`} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    <FileSpreadsheet className="h-4 w-4" /> Excel financeiro
                                </a>
                            </>
                        )}
                    </div>
                </div>

                <div className="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                    <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900">
                        <Filter className="h-4 w-4 text-gray-400" />
                        Filtros
                    </div>
                    <div className="grid grid-cols-1 gap-4 xl:grid-cols-[260px_260px_minmax(0,1fr)]">
                        <div className="grid grid-cols-2 gap-2">
                            <div>
                                <label className="block text-xs font-medium text-gray-500">De</label>
                                <input type="date" value={from} onChange={(event) => setFrom(event.target.value)} className="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-500">Ate</label>
                                <input type="date" value={to} onChange={(event) => setTo(event.target.value)} className="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                            </div>
                        </div>

                        <div>
                            <div className="flex items-center justify-between">
                                <label className="block text-xs font-medium text-gray-500">Condominios</label>
                                <button type="button" onClick={() => setCondominiumIds([])} className="text-xs font-medium text-blue-600 hover:text-blue-700">Todos</button>
                            </div>
                            <div className="mt-1 max-h-28 overflow-y-auto rounded-lg border border-gray-200 bg-white p-2">
                                {report.available_condominiums.map((condominium) => {
                                    const checked = condominiumIds.length === 0 || condominiumIds.includes(condominium.value);
                                    return (
                                        <label key={condominium.value} className="flex items-center gap-2 rounded px-2 py-1 text-sm text-gray-700 hover:bg-gray-50">
                                            <input
                                                type="checkbox"
                                                checked={checked}
                                                onChange={() => setCondominiumIds(condominiumIds.length === 0 ? allCondominiumIds.filter((id) => id !== condominium.value) : toggle(condominiumIds, condominium.value))}
                                                className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            />
                                            <span className="truncate">{condominium.label}</span>
                                        </label>
                                    );
                                })}
                            </div>
                        </div>

                        <div>
                            <div className="flex items-center justify-between">
                                <label className="block text-xs font-medium text-gray-500">Modulos</label>
                                <button type="button" onClick={() => setModules([])} className="text-xs font-medium text-blue-600 hover:text-blue-700">Todos</button>
                            </div>
                            <div className="mt-1 flex flex-wrap gap-2">
                                {report.available_modules.map((module) => {
                                    const checked = modules.length === 0 || modules.includes(module.value);
                                    return (
                                        <button
                                            key={module.value}
                                            type="button"
                                            onClick={() => setModules(modules.length === 0 ? report.available_modules.map((item) => item.value).filter((item) => item !== module.value) : toggle(modules, module.value))}
                                            className={`rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors ${checked ? moduleTone[module.value] ?? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-gray-200 bg-white text-gray-500 hover:bg-gray-50'}`}
                                        >
                                            {module.label}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    </div>

                    <div className="mt-4 flex flex-wrap items-center justify-between gap-2">
                        <p className="text-xs text-gray-500">
                            {selectedCondominiums.length} condominio(s), {selectedModules.length} modulo(s)
                        </p>
                        <div className="flex gap-2">
                            <button type="button" onClick={reset} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <RefreshCcw className="h-4 w-4" /> Limpar
                            </button>
                            <button type="button" onClick={apply} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                Aplicar
                            </button>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <StatCard label="Estrutura" value={`${number(summary.units)} unidades`} detail={`${number(summary.residents)} pessoas vinculadas`} icon={Building2} tone="bg-blue-600" />
                    <StatCard label="Recebido" value={brl(summary.financial.received)} detail={`Saldo ${brl(summary.financial.balance)}`} icon={Wallet} tone="bg-emerald-600" />
                    <StatCard label="Inadimplencia" value={brl(summary.financial.overdue_charges)} detail={`${number(summary.financial.delinquent_units)} unidade(s)`} icon={AlertTriangle} tone="bg-red-600" />
                    <StatCard label="Operacao" value={`${number(summary.operations.open_occurrences)} chamados`} detail={`${number(summary.operations.sla_overdue)} SLA vencido(s)`} icon={Activity} tone="bg-amber-500" />
                    <StatCard label="Risco alto" value={number(summary.risk.high)} detail={`${number(summary.operations.maintenance_overdue + summary.operations.works_overdue)} prazo(s) vencido(s)`} icon={TrendingUp} tone="bg-indigo-600" />
                </div>

                <div className="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                    <div className="border-b border-gray-100 px-4 py-3">
                        <h2 className="text-sm font-semibold text-gray-900">Comparativo por condominio</h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-[1120px] w-full text-sm">
                            <thead className="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th className="px-4 py-3">Condominio</th>
                                    <th className="px-4 py-3 text-right">Unidades</th>
                                    <th className="px-4 py-3 text-right">Saldo</th>
                                    <th className="px-4 py-3 text-right">Inadimplencia</th>
                                    <th className="px-4 py-3 text-right">Ocorrencias</th>
                                    <th className="px-4 py-3 text-right">SLA</th>
                                    <th className="px-4 py-3 text-right">Manut.</th>
                                    <th className="px-4 py-3 text-right">Obras</th>
                                    <th className="px-4 py-3 text-right">Docs</th>
                                    <th className="px-4 py-3 text-center">Risco</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {report.by_condominium.length === 0 && (
                                    <tr>
                                        <td colSpan={10} className="px-4 py-10 text-center text-gray-400">Sem dados no escopo selecionado.</td>
                                    </tr>
                                )}
                                {report.by_condominium.map((row) => (
                                    <tr key={row.condominium.id} className="hover:bg-gray-50/70">
                                        <td className="px-4 py-3">
                                            <p className="font-semibold text-gray-900">{row.condominium.name}</p>
                                            <p className="text-xs text-gray-500">{[row.condominium.city, row.condominium.state].filter(Boolean).join('/') || 'Localidade nao informada'}</p>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <p className="font-medium text-gray-900">{number(row.structure.units)}</p>
                                            <p className="text-xs text-gray-500">{percent(row.structure.occupied_units, row.structure.units)} ocup.</p>
                                        </td>
                                        <td className={`px-4 py-3 text-right font-semibold ${row.financial.balance < 0 ? 'text-red-600' : 'text-gray-900'}`}>{brl(row.financial.balance)}</td>
                                        <td className="px-4 py-3 text-right">
                                            <p className="font-semibold text-red-600">{brl(row.financial.overdue_charges)}</p>
                                            <p className="text-xs text-gray-500">{number(row.financial.delinquent_units)} un.</p>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <p className="font-medium text-gray-900">{number(row.occurrences.open)}</p>
                                            <p className="text-xs text-gray-500">{number(row.occurrences.created)} novas</p>
                                        </td>
                                        <td className="px-4 py-3 text-right font-semibold text-amber-700">{number(row.occurrences.sla_overdue)}</td>
                                        <td className="px-4 py-3 text-right">
                                            <p className="font-medium text-gray-900">{number(row.maintenance.active)}</p>
                                            <p className="text-xs text-red-600">{number(row.maintenance.overdue)} atras.</p>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <p className="font-medium text-gray-900">{number(row.works.active)}</p>
                                            <p className="text-xs text-red-600">{number(row.works.overdue)} atras.</p>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <p className="font-medium text-gray-900">{number(row.documents.current)}</p>
                                            <p className="text-xs text-red-600">{number(row.documents.expired)} venc.</p>
                                        </td>
                                        <td className="px-4 py-3 text-center"><RiskBadge risk={row.risk} /></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <div className="rounded-lg border border-gray-100 bg-white shadow-sm">
                        <div className="border-b border-gray-100 px-4 py-3">
                            <h2 className="text-sm font-semibold text-gray-900">Serie mensal</h2>
                        </div>
                        <div className="divide-y divide-gray-50">
                            {report.monthly.length === 0 && <p className="px-4 py-8 text-center text-sm text-gray-400">Sem serie mensal.</p>}
                            {report.monthly.map((month) => (
                                <div key={month.month} className="grid grid-cols-1 gap-3 px-4 py-3 lg:grid-cols-[90px_minmax(0,1fr)_220px] lg:items-center">
                                    <div>
                                        <p className="text-sm font-semibold capitalize text-gray-900">{month.label}</p>
                                        <p className="text-xs text-gray-500">{number(month.occurrences + month.reservations + month.maintenance_executions + month.works_completed)} mov.</p>
                                    </div>
                                    <div className="space-y-1.5">
                                        <div className="flex items-center gap-2">
                                            <span className="w-16 text-xs text-gray-500">Recebido</span>
                                            <div className="h-2 flex-1 rounded-full bg-gray-100">
                                                <div className="h-2 rounded-full bg-emerald-500" style={{ width: `${Math.min(100, (month.received / maxMonthlyValue) * 100)}%` }} />
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className="w-16 text-xs text-gray-500">Despesas</span>
                                            <div className="h-2 flex-1 rounded-full bg-gray-100">
                                                <div className="h-2 rounded-full bg-red-500" style={{ width: `${Math.min(100, (month.expenses / maxMonthlyValue) * 100)}%` }} />
                                            </div>
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-2 gap-2 text-xs">
                                        <span className="rounded bg-emerald-50 px-2 py-1 text-emerald-700">{brl(month.received)}</span>
                                        <span className="rounded bg-red-50 px-2 py-1 text-red-700">{brl(month.expenses)}</span>
                                        <span className="rounded bg-amber-50 px-2 py-1 text-amber-700">{number(month.occurrences)} ocorr.</span>
                                        <span className="rounded bg-blue-50 px-2 py-1 text-blue-700">{number(month.reservations)} reserv.</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="space-y-4">
                        <RankingList title="Maior inadimplencia" items={report.rankings.financial_risk} money />
                        <RankingList title="Maior risco operacional" items={report.rankings.operational_risk} />
                        <RankingList title="Mais contas pagas" items={report.rankings.expenses} money />
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-3 lg:grid-cols-3">
                    <StatCard label="Manutencoes" value={`${number(summary.operations.maintenance_due_soon)} a vencer`} detail={`${number(summary.operations.maintenance_overdue)} atrasada(s)`} icon={Wrench} tone="bg-amber-500" />
                    <StatCard label="Obras" value={`${number(summary.operations.active_works)} ativas`} detail={`${number(summary.operations.works_overdue)} fora do prazo`} icon={Hammer} tone="bg-indigo-600" />
                    <StatCard label="Documentos" value={`${number(summary.operations.documents_expiring)} vencendo`} detail={`${number(summary.operations.documents_expired)} vencido(s)`} icon={FileText} tone="bg-violet-600" />
                </div>
            </div>
        </AppLayout>
    );
}
