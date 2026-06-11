import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import DashboardHeader from '@/Components/Dashboard/DashboardHeader';
import DashboardFilters from '@/Components/Dashboard/DashboardFilters';
import WidgetCard from '@/Components/Dashboard/WidgetCard';
import CustomizePanel from '@/Components/Dashboard/CustomizePanel';
import type { ActiveFilters, DashboardPageProps } from '@/Components/Dashboard/types';

export default function DashboardIndex({
    meta,
    data,
    filters,
    activeFilters,
    preferences,
    header,
}: DashboardPageProps) {
    const [customizing, setCustomizing] = useState(false);
    const [panelOpen, setPanelOpen] = useState(false);
    const [hidden, setHidden] = useState<string[]>(preferences.hidden ?? []);
    const [order, setOrder] = useState<string[]>(preferences.order ?? []);
    const [refreshNonce, setRefreshNonce] = useState(0);

    // Querystring repassada aos widgets lazy para respeitar os filtros globais.
    const queryString = useMemo(() => {
        const params = new URLSearchParams();
        if (activeFilters.period) params.set('period', activeFilters.period);
        if (activeFilters.condominium) params.set('condominium', activeFilters.condominium);
        if (activeFilters.status) params.set('status', activeFilters.status);
        const s = params.toString();
        return s ? `?${s}` : '';
    }, [activeFilters]);

    // Widgets visíveis, ordenados pela preferência do usuário e sem os ocultos.
    const visible = useMemo(() => {
        const rank = new Map(order.map((k, i) => [k, i]));
        return [...meta]
            .filter((m) => !hidden.includes(m.key))
            .sort((a, b) => (rank.get(a.key) ?? 999) - (rank.get(b.key) ?? 999) || a.order - b.order);
    }, [meta, hidden, order]);

    const applyFilters = (patch: Partial<ActiveFilters>) => {
        const next = { ...activeFilters, ...patch };
        const query: Record<string, string> = {};
        if (next.period) query.period = next.period;
        if (next.condominium) query.condominium = next.condominium;
        if (next.status) query.status = next.status;
        router.get('/dashboard', query, { preserveState: true, preserveScroll: true, replace: true });
    };

    const refresh = () => {
        router.reload();
        setRefreshNonce((n) => n + 1);
    };

    const savePreferences = (nextHidden: string[], nextOrder: string[]) => {
        setHidden(nextHidden);
        setOrder(nextOrder);
        setPanelOpen(false);
        setCustomizing(false);
        router.put(
            '/dashboard/preferences',
            { hidden_widgets: nextHidden, widget_order: nextOrder },
            { preserveScroll: true, preserveState: true },
        );
    };

    const hideWidget = (key: string) => {
        const next = [...new Set([...hidden, key])];
        setHidden(next);
        router.put(
            '/dashboard/preferences',
            { hidden_widgets: next, widget_order: order },
            { preserveScroll: true, preserveState: true },
        );
    };

    const toggleCustomize = () => {
        setCustomizing((c) => !c);
        setPanelOpen((o) => !o);
    };

    return (
        <AppLayout>
            <Head title="Dashboard" />

            <div className="space-y-5">
                <DashboardHeader
                    header={header}
                    onRefresh={refresh}
                    onCustomize={toggleCustomize}
                    customizing={customizing}
                />

                <DashboardFilters filters={filters} active={activeFilters} onChange={applyFilters} />

                {visible.length === 0 ? (
                    <div className="rounded-2xl border-2 border-dashed border-gray-200 bg-white p-12 text-center">
                        <p className="text-sm text-gray-500">
                            Nenhum indicador disponível. Use{' '}
                            <button onClick={toggleCustomize} className="font-medium text-blue-600 hover:underline">
                                Personalizar
                            </button>{' '}
                            para reativar widgets.
                        </p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {visible.map((m) => (
                            <WidgetCard
                                key={`${m.key}:${queryString}:${refreshNonce}`}
                                meta={m}
                                initialPayload={data[m.key]}
                                queryString={queryString}
                                customizing={customizing}
                                onHide={hideWidget}
                            />
                        ))}
                    </div>
                )}
            </div>

            <CustomizePanel
                open={panelOpen}
                meta={meta}
                hidden={hidden}
                order={order}
                onClose={() => {
                    setPanelOpen(false);
                    setCustomizing(false);
                }}
                onSave={savePreferences}
            />
        </AppLayout>
    );
}
