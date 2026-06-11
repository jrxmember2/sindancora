import { Link } from '@inertiajs/react';
import Chart from 'react-apexcharts';
import type { ApexOptions } from 'apexcharts';
import { ArrowDownRight, ArrowUpRight, ArrowRight, ChevronRight } from 'lucide-react';
import { brl, num, pct } from '@/lib/format';
import { baseChartOptions, color, hex } from '@/lib/dashboardTheme';
import { icon } from './icons';
import type { WidgetPayload } from './types';

/* ─────────────────────────── KPIs ─────────────────────────── */

export function KpiWidget({ p }: { p: WidgetPayload }) {
    const Icon = icon(p.icon as string);
    const c = color(p.color as string);
    return (
        <div className="flex items-start justify-between">
            <div className="min-w-0">
                <p className="text-sm text-gray-500">{p.label as string}</p>
                <p className="mt-1 text-3xl font-semibold tracking-tight text-gray-900">
                    {(p.formatted as string) ?? num(p.value as number)}
                </p>
                {p.caption ? <p className="mt-1 text-xs text-gray-400">{p.caption as string}</p> : null}
            </div>
            <div className={`rounded-xl ${c.bg} p-2.5`}>
                <Icon className={`h-5 w-5 ${c.text}`} />
            </div>
        </div>
    );
}

export function KpiTrendWidget({ p }: { p: WidgetPayload }) {
    const c = color(p.color as string);
    const dir = p.direction as string;
    const delta = (p.delta as number) ?? 0;
    const spark = (p.sparkline as number[]) ?? [];

    const DeltaIcon = dir === 'up' ? ArrowUpRight : dir === 'down' ? ArrowDownRight : ArrowRight;
    const deltaColor = dir === 'up' ? 'text-emerald-600 bg-emerald-50' : dir === 'down' ? 'text-red-600 bg-red-50' : 'text-gray-500 bg-gray-100';

    const options: ApexOptions = {
        chart: { sparkline: { enabled: true }, toolbar: { show: false } },
        stroke: { curve: 'smooth', width: 2 },
        colors: [c.hex],
        fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0 } },
        tooltip: { enabled: false },
    };

    return (
        <div>
            <div className="flex items-start justify-between">
                <div className="min-w-0">
                    <p className="text-sm text-gray-500">{p.label as string}</p>
                    <p className="mt-1 text-3xl font-semibold tracking-tight text-gray-900">{p.formatted as string}</p>
                </div>
                <span className={`inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-xs font-semibold ${deltaColor}`}>
                    <DeltaIcon className="h-3.5 w-3.5" />
                    {pct(Math.abs(delta))}
                </span>
            </div>
            {spark.length > 0 && (
                <div className="-mx-2 mt-2">
                    <Chart type="area" height={48} options={options} series={[{ name: p.label as string, data: spark }]} />
                </div>
            )}
            {p.caption ? <p className="mt-1 text-xs text-gray-400">{p.caption as string}</p> : null}
        </div>
    );
}

/* ───────────────────────── Gráficos ───────────────────────── */

function axisChart(type: 'line' | 'area' | 'bar', p: WidgetPayload) {
    const format = p.format as 'currency' | 'number' | undefined;
    const tokens = (p.colors as string[]) ?? [p.color as string];
    const colors = tokens.map((t) => hex(t));
    const options: ApexOptions = {
        ...baseChartOptions(colors, format),
        xaxis: { ...baseChartOptions(colors, format).xaxis, categories: (p.categories as string[]) ?? [] },
        stroke: { curve: 'smooth', width: type === 'bar' ? 0 : 3 },
        fill: type === 'area' ? { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } } : {},
        plotOptions: type === 'bar' ? { bar: { borderRadius: 4, columnWidth: '55%' } } : {},
    };
    return <Chart type={type} height={260} options={options} series={(p.series as ApexOptions['series']) ?? []} />;
}

export const LineChartWidget = ({ p }: { p: WidgetPayload }) => axisChart('line', p);
export const AreaChartWidget = ({ p }: { p: WidgetPayload }) => axisChart('area', p);
export const BarChartWidget = ({ p }: { p: WidgetPayload }) => axisChart('bar', p);

export function DonutChartWidget({ p }: { p: WidgetPayload }) {
    const tokens = (p.colors as string[]) ?? [];
    const colors = tokens.map((t) => hex(t));
    const series = (p.series as number[]) ?? [];
    const options: ApexOptions = {
        ...baseChartOptions(colors),
        labels: (p.labels as string[]) ?? [],
        legend: { position: 'bottom', fontFamily: 'Figtree, sans-serif', markers: { strokeWidth: 0 } },
        stroke: { width: 2 },
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: (p.totalLabel as string) ?? 'Total',
                            formatter: () => num(series.reduce((a, b) => a + b, 0)),
                        },
                    },
                },
            },
        },
    };
    return <Chart type="donut" height={280} options={options} series={series} />;
}

export function GaugeWidget({ p }: { p: WidgetPayload }) {
    const c = color(p.color as string);
    const value = (p.value as number) ?? 0;
    const options: ApexOptions = {
        chart: { fontFamily: 'Figtree, sans-serif', toolbar: { show: false } },
        colors: [c.hex],
        plotOptions: {
            radialBar: {
                hollow: { size: '62%' },
                track: { background: '#f1f5f9' },
                dataLabels: {
                    name: { offsetY: 22, color: '#94a3b8', fontSize: '12px' },
                    value: { offsetY: -12, fontSize: '26px', fontWeight: 700, color: '#0f172a', formatter: (v) => `${Math.round(Number(v))}%` },
                },
            },
        },
        labels: [p.label as string],
        stroke: { lineCap: 'round' },
    };
    return (
        <div>
            <Chart type="radialBar" height={220} options={options} series={[value]} />
            {p.formatted ? <p className="text-center text-sm font-medium text-gray-700">{p.formatted as string}</p> : null}
            {p.caption ? <p className={`mt-0.5 text-center text-xs ${c.text}`}>{p.caption as string}</p> : null}
        </div>
    );
}

export const RadialWidget = GaugeWidget;

/* ───────────────────────── Listas ─────────────────────────── */

export function RankingWidget({ p }: { p: WidgetPayload }) {
    const items = (p.items as { label: string; value: number; formatted: string }[]) ?? [];
    const max = Math.max(...items.map((i) => i.value), 1);
    const c = color(p.color as string);
    return (
        <ul className="space-y-3">
            {items.map((item, idx) => (
                <li key={idx}>
                    <div className="mb-1 flex items-center justify-between text-sm">
                        <span className="min-w-0 flex-1 truncate text-gray-700">
                            <span className="mr-1.5 text-xs font-semibold text-gray-400">{idx + 1}º</span>
                            {item.label}
                        </span>
                        <span className="ml-2 font-semibold text-gray-900">{item.formatted}</span>
                    </div>
                    <div className="h-2 rounded-full bg-gray-100">
                        <div className={`h-2 rounded-full ${c.solid}`} style={{ width: `${(item.value / max) * 100}%` }} />
                    </div>
                </li>
            ))}
        </ul>
    );
}

export function ActivityTimelineWidget({ p }: { p: WidgetPayload }) {
    const items = (p.items as { title: string; subtitle: string; time?: string; color?: string; href?: string }[]) ?? [];
    return (
        <ul className="space-y-1">
            {items.map((item, idx) => {
                const c = color(item.color);
                const Row = (
                    <div className="flex items-start gap-3 rounded-lg px-2 py-2 transition hover:bg-gray-50">
                        <span className={`mt-1.5 h-2 w-2 flex-shrink-0 rounded-full ${c.solid}`} />
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium text-gray-800">{item.title}</p>
                            <p className="truncate text-xs text-gray-500">{item.subtitle}</p>
                        </div>
                        {item.time ? <span className="flex-shrink-0 text-xs text-gray-400">{item.time}</span> : null}
                    </div>
                );
                return (
                    <li key={idx}>
                        {item.href ? <Link href={item.href}>{Row}</Link> : Row}
                    </li>
                );
            })}
        </ul>
    );
}

export function AlertWidget({ p }: { p: WidgetPayload }) {
    const items = (p.items as { title: string; subtitle: string; value?: string; href?: string; overdue?: boolean }[]) ?? [];
    const level = (p.level as string) ?? 'info';
    const tone = level === 'critical' ? color('red') : level === 'warning' ? color('amber') : color('blue');

    if (items.length === 0) {
        return <p className="py-6 text-center text-sm text-gray-400">{(p.emptyText as string) ?? 'Nada por aqui.'}</p>;
    }

    return (
        <div className="space-y-2">
            {(p.overdue_count as number) > 0 && (
                <div className={`rounded-lg ${tone.bg} px-3 py-2 text-xs font-medium ${tone.text}`}>
                    {p.overdue_count as number} já vencido(s)
                </div>
            )}
            <ul className="space-y-1">
                {items.map((item, idx) => {
                    const Row = (
                        <div className="flex items-center gap-3 rounded-lg px-2 py-2 transition hover:bg-gray-50">
                            <span className={`h-2 w-2 flex-shrink-0 rounded-full ${item.overdue ? 'bg-red-500' : tone.solid}`} />
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium text-gray-800">{item.title}</p>
                                <p className="truncate text-xs text-gray-500">{item.subtitle}</p>
                            </div>
                            {item.value ? <span className="flex-shrink-0 text-sm font-semibold text-gray-900">{item.value}</span> : null}
                        </div>
                    );
                    return <li key={idx}>{item.href ? <Link href={item.href}>{Row}</Link> : Row}</li>;
                })}
            </ul>
        </div>
    );
}

export function QuickActionsWidget({ p }: { p: WidgetPayload }) {
    const actions = (p.actions as { label: string; href: string; icon: string; color: string }[]) ?? [];
    return (
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
            {actions.map((a, idx) => {
                const Icon = icon(a.icon);
                const c = color(a.color);
                return (
                    <Link
                        key={idx}
                        href={a.href}
                        className="group flex flex-col items-center gap-2 rounded-xl border border-gray-100 bg-white px-3 py-4 text-center transition hover:-translate-y-0.5 hover:border-gray-200 hover:shadow-sm"
                    >
                        <span className={`rounded-xl ${c.bg} p-2.5 transition group-hover:scale-105`}>
                            <Icon className={`h-5 w-5 ${c.text}`} />
                        </span>
                        <span className="text-xs font-medium text-gray-700">{a.label}</span>
                    </Link>
                );
            })}
        </div>
    );
}

export function SummaryTableWidget({ p }: { p: WidgetPayload }) {
    const columns = (p.columns as { key: string; label: string; badge?: boolean }[]) ?? [];
    const rows = (p.rows as Record<string, string>[]) ?? [];
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                        {columns.map((col) => (
                            <th key={col.key} className="pb-2 pr-4 font-medium">{col.label}</th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                    {rows.map((row, idx) => (
                        <tr key={idx} className="text-gray-700">
                            {columns.map((col) => (
                                <td key={col.key} className="py-2.5 pr-4">
                                    {col.badge ? (
                                        <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${color(row[`${col.key.replace('_label', '')}_color`] ?? 'gray').badgeBg} ${color(row[`${col.key.replace('_label', '')}_color`] ?? 'gray').badgeText}`}>
                                            {row[col.key]}
                                        </span>
                                    ) : (
                                        row[col.key]
                                    )}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export function StatusCardWidget({ p }: { p: WidgetPayload }) {
    const c = color(p.color as string);
    return (
        <div className="flex items-center justify-between">
            <div>
                <p className="text-sm text-gray-500">{p.label as string}</p>
                {p.value ? <p className="mt-1 text-2xl font-semibold text-gray-900">{p.value as string}</p> : null}
            </div>
            <span className={`inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold ${c.badgeBg} ${c.badgeText}`}>
                {p.statusLabel as string}
            </span>
        </div>
    );
}

/** Rodapé opcional "ver tudo" para widgets de lista. */
export function SeeAllLink({ href }: { href?: string }) {
    if (!href) return null;
    return (
        <Link href={href} className="mt-3 inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-700">
            Ver tudo <ChevronRight className="h-3.5 w-3.5" />
        </Link>
    );
}
