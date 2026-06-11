// Formatação pt-BR compartilhada pelos widgets do dashboard.

const brlFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

const numberFormatter = new Intl.NumberFormat('pt-BR');

/** Moeda em Real: 1234.5 → "R$ 1.234,50". */
export function brl(value: number): string {
    return brlFormatter.format(value ?? 0);
}

/** Número com separador de milhar pt-BR. */
export function num(value: number): string {
    return numberFormatter.format(value ?? 0);
}

/** Percentual com 1 casa: 0.5 → "0,5%". */
export function pct(value: number, digits = 1): string {
    return `${(value ?? 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: digits,
    })}%`;
}

/** Compacto para eixos: 12345 → "12,3 mil"; 1200000 → "1,2 mi". */
export function compact(value: number): string {
    const abs = Math.abs(value ?? 0);
    if (abs >= 1_000_000) return `${(value / 1_000_000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} mi`;
    if (abs >= 1_000) return `${(value / 1_000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} mil`;
    return num(value);
}

/** Moeda compacta para eixos de gráfico. */
export function compactBrl(value: number): string {
    const abs = Math.abs(value ?? 0);
    if (abs >= 1_000_000) return `R$ ${(value / 1_000_000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} mi`;
    if (abs >= 1_000) return `R$ ${(value / 1_000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} mil`;
    return brl(value);
}

/** Data ISO → "11/06/2026". */
export function dateBr(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString('pt-BR');
}

/** Hora relativa amigável a partir de um ISO. */
export function timeAgo(iso: string | null): string {
    if (!iso) return '';
    const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
    if (diff < 60) return 'agora';
    if (diff < 3600) return `há ${Math.floor(diff / 60)} min`;
    if (diff < 86400) return `há ${Math.floor(diff / 3600)} h`;
    return `há ${Math.floor(diff / 86400)} d`;
}
