import { CalendarDays, Building2, Filter } from 'lucide-react';
import type { ActiveFilters, DashboardFiltersData } from './types';

/** Barra de filtros globais. As mudanças sobem para a página, que recarrega via Inertia. */
export default function DashboardFilters({
    filters,
    active,
    onChange,
}: {
    filters: DashboardFiltersData;
    active: ActiveFilters;
    onChange: (patch: Partial<ActiveFilters>) => void;
}) {
    const selectClass =
        'rounded-lg border-gray-200 bg-white py-2 pl-9 pr-8 text-sm text-gray-700 shadow-sm focus:border-blue-400 focus:ring-blue-400';

    return (
        <div className="flex flex-wrap items-center gap-3 rounded-2xl border border-gray-100 bg-white p-3 shadow-sm">
            <div className="relative">
                <CalendarDays className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <select
                    value={active.period}
                    onChange={(e) => onChange({ period: e.target.value })}
                    className={selectClass}
                >
                    {filters.periods.map((p) => (
                        <option key={p.value} value={p.value}>{p.label}</option>
                    ))}
                </select>
            </div>

            {filters.condominiums.length > 1 && (
                <div className="relative">
                    <Building2 className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <select
                        value={active.condominium ?? ''}
                        onChange={(e) => onChange({ condominium: e.target.value || null })}
                        className={selectClass}
                    >
                        <option value="">Todos os condomínios</option>
                        {filters.condominiums.map((c) => (
                            <option key={c.id} value={c.id}>{c.name}</option>
                        ))}
                    </select>
                </div>
            )}

            <div className="relative">
                <Filter className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <select
                    value={active.status ?? ''}
                    onChange={(e) => onChange({ status: e.target.value || null })}
                    className={selectClass}
                >
                    <option value="">Todos os status</option>
                    {filters.statuses.map((s) => (
                        <option key={s.value} value={s.value}>{s.label}</option>
                    ))}
                </select>
            </div>
        </div>
    );
}
