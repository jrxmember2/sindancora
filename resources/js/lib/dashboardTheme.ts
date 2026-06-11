// Tokens de cor e fábrica de opções base do ApexCharts para o dashboard modular.
import type { ApexOptions } from 'apexcharts';
import { brl, compact, compactBrl, num } from './format';

export type ColorToken =
    | 'blue' | 'indigo' | 'violet' | 'emerald' | 'amber' | 'red' | 'sky' | 'gray';

interface ColorStyle {
    hex: string;
    text: string;
    bg: string;
    solid: string;
    gradient: string;
    badgeBg: string;
    badgeText: string;
}

/** Paleta profissional, colorida com bom senso. */
export const COLORS: Record<ColorToken, ColorStyle> = {
    blue:    { hex: '#2563eb', text: 'text-blue-600',    bg: 'bg-blue-50',    solid: 'bg-blue-600',    gradient: 'from-blue-500 to-blue-600',       badgeBg: 'bg-blue-50',    badgeText: 'text-blue-700' },
    indigo:  { hex: '#4f46e5', text: 'text-indigo-600',  bg: 'bg-indigo-50',  solid: 'bg-indigo-600',  gradient: 'from-indigo-500 to-indigo-600',   badgeBg: 'bg-indigo-50',  badgeText: 'text-indigo-700' },
    violet:  { hex: '#7c3aed', text: 'text-violet-600',  bg: 'bg-violet-50',  solid: 'bg-violet-600',  gradient: 'from-violet-500 to-violet-600',   badgeBg: 'bg-violet-50',  badgeText: 'text-violet-700' },
    emerald: { hex: '#059669', text: 'text-emerald-600', bg: 'bg-emerald-50', solid: 'bg-emerald-600', gradient: 'from-emerald-500 to-emerald-600', badgeBg: 'bg-emerald-50', badgeText: 'text-emerald-700' },
    amber:   { hex: '#d97706', text: 'text-amber-600',   bg: 'bg-amber-50',   solid: 'bg-amber-500',   gradient: 'from-amber-400 to-amber-500',     badgeBg: 'bg-amber-50',   badgeText: 'text-amber-700' },
    red:     { hex: '#dc2626', text: 'text-red-600',     bg: 'bg-red-50',     solid: 'bg-red-600',     gradient: 'from-red-500 to-red-600',         badgeBg: 'bg-red-50',     badgeText: 'text-red-700' },
    sky:     { hex: '#0284c7', text: 'text-sky-600',     bg: 'bg-sky-50',     solid: 'bg-sky-600',     gradient: 'from-sky-400 to-sky-600',         badgeBg: 'bg-sky-50',     badgeText: 'text-sky-700' },
    gray:    { hex: '#64748b', text: 'text-slate-600',   bg: 'bg-slate-50',   solid: 'bg-slate-500',   gradient: 'from-slate-400 to-slate-600',     badgeBg: 'bg-slate-100',  badgeText: 'text-slate-700' },
};

export function color(token?: string): ColorStyle {
    return COLORS[(token as ColorToken)] ?? COLORS.blue;
}

export function hex(token?: string): string {
    return color(token).hex;
}

/** Cor de acento por módulo, usada nos cabeçalhos dos cards. */
export const MODULE_COLOR: Record<string, ColorToken> = {
    general: 'gray',
    financial: 'emerald',
    occurrences: 'amber',
    reservations: 'violet',
    documents: 'sky',
    maintenance: 'blue',
    works: 'indigo',
};

export function moduleColor(module: string): ColorToken {
    return MODULE_COLOR[module] ?? 'blue';
}

const FONT = 'Figtree, ui-sans-serif, system-ui, sans-serif';

type ValueFormat = 'currency' | 'number';

function valueFormatter(format?: ValueFormat) {
    return format === 'currency' ? brl : num;
}

function axisFormatter(format?: ValueFormat) {
    return format === 'currency' ? compactBrl : compact;
}

/** Opções base comuns a todos os gráficos (sem toolbar, fonte e tooltip pt-BR). */
export function baseChartOptions(colors: string[], format?: ValueFormat): ApexOptions {
    return {
        chart: {
            fontFamily: FONT,
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { speed: 400 },
            parentHeightOffset: 0,
        },
        colors,
        dataLabels: { enabled: false },
        grid: {
            borderColor: '#f1f5f9',
            strokeDashArray: 4,
            padding: { left: 8, right: 8, top: 0 },
        },
        legend: {
            position: 'top',
            horizontalAlign: 'right',
            fontFamily: FONT,
            markers: { strokeWidth: 0 },
            itemMargin: { horizontal: 8 },
        },
        tooltip: {
            style: { fontFamily: FONT },
            y: { formatter: (v: number) => valueFormatter(format)(v) },
        },
        xaxis: {
            labels: { style: { fontFamily: FONT, colors: '#94a3b8', fontSize: '11px' } },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: {
            labels: {
                style: { fontFamily: FONT, colors: '#94a3b8', fontSize: '11px' },
                formatter: (v: number) => axisFormatter(format)(v),
            },
        },
    };
}
