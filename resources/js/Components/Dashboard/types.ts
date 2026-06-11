// Tipos compartilhados do dashboard modular (espelham o payload do backend).

export type WidgetType =
    | 'kpi'
    | 'kpi_trend'
    | 'line'
    | 'bar'
    | 'area'
    | 'donut'
    | 'gauge'
    | 'radial'
    | 'ranking'
    | 'timeline'
    | 'alert'
    | 'quick_actions'
    | 'summary_table'
    | 'status_card';

export type WidgetSize = 'small' | 'medium' | 'large' | 'wide' | 'full';

export interface WidgetMeta {
    key: string;
    module: string;
    name: string;
    description: string;
    type: WidgetType;
    size: WidgetSize;
    lazy: boolean;
    order: number;
    config: Record<string, unknown>;
}

/** Payload genérico de um widget; o shape concreto depende do tipo. */
export type WidgetPayload = Record<string, unknown> & {
    empty?: boolean;
    error?: boolean;
    message?: string;
};

export interface SelectOption {
    value: string;
    label: string;
}

export interface DashboardFiltersData {
    condominiums: { id: string; name: string }[];
    modules: SelectOption[];
    periods: SelectOption[];
    statuses: SelectOption[];
}

export interface ActiveFilters {
    period: string;
    condominium: string | null;
    status: string | null;
}

export interface DashboardPreferences {
    hidden: string[];
    order: string[];
}

export interface DashboardHeaderData {
    greeting: string;
    user_name: string;
    period_label: string;
    updated_at: string;
    condominium_count: number;
}

export interface DashboardPageProps {
    meta: WidgetMeta[];
    data: Record<string, WidgetPayload>;
    filters: DashboardFiltersData;
    activeFilters: ActiveFilters;
    preferences: DashboardPreferences;
    header: DashboardHeaderData;
}
