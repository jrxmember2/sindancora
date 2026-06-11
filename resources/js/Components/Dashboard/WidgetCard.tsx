import { useCallback, useEffect, useRef, useState } from 'react';
import { EyeOff, RefreshCw } from 'lucide-react';
import { color, moduleColor } from '@/lib/dashboardTheme';
import WidgetRenderer from './WidgetRenderer';
import { WidgetSkeleton } from './WidgetStates';
import type { WidgetMeta, WidgetPayload } from './types';

/** Grade de 4 colunas (xl): tamanho do widget → col-span responsivo. */
const SPAN: Record<string, string> = {
    small: 'sm:col-span-1',
    medium: 'sm:col-span-2 xl:col-span-2',
    large: 'sm:col-span-2 xl:col-span-2',
    wide: 'sm:col-span-2 xl:col-span-3',
    full: 'sm:col-span-2 xl:col-span-4',
};

export default function WidgetCard({
    meta,
    initialPayload,
    queryString,
    customizing,
    onHide,
}: {
    meta: WidgetMeta;
    initialPayload?: WidgetPayload;
    queryString: string;
    customizing: boolean;
    onHide: (key: string) => void;
}) {
    const [payload, setPayload] = useState<WidgetPayload | null>(initialPayload ?? null);
    const [loading, setLoading] = useState<boolean>(meta.lazy && !initialPayload);
    const fetched = useRef(false);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const res = await fetch(`/dashboard/widgets/${encodeURIComponent(meta.key)}${queryString}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const json = await res.json();
            setPayload(json.data as WidgetPayload);
        } catch {
            setPayload({ error: true, message: 'Falha ao carregar.' });
        } finally {
            setLoading(false);
        }
    }, [meta.key, queryString]);

    // Widgets lazy buscam ao montar; ao mudar de filtro (queryString), recarrega o que já tinha sido carregado.
    useEffect(() => {
        if (meta.lazy && !initialPayload && !fetched.current) {
            fetched.current = true;
            void load();
        }
    }, [meta.lazy, initialPayload, load]);

    const mc = color(moduleColor(meta.module));

    return (
        <div className={`${SPAN[meta.size] ?? SPAN.small} col-span-1 flex flex-col rounded-2xl border border-gray-100 bg-white p-5 shadow-sm transition hover:shadow-md`}>
            <div className="mb-3 flex items-start justify-between gap-2">
                <div className="flex min-w-0 items-center gap-2">
                    <span className={`h-2.5 w-2.5 flex-shrink-0 rounded-full ${mc.solid}`} />
                    <div className="min-w-0">
                        <h3 className="truncate text-sm font-semibold text-gray-800" title={meta.description}>
                            {meta.name}
                        </h3>
                    </div>
                </div>
                <div className="flex flex-shrink-0 items-center gap-1">
                    <button
                        onClick={() => void load()}
                        className="rounded-lg p-1 text-gray-300 transition hover:bg-gray-50 hover:text-gray-500"
                        title="Atualizar"
                    >
                        <RefreshCw className={`h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} />
                    </button>
                    {customizing && (
                        <button
                            onClick={() => onHide(meta.key)}
                            className="rounded-lg p-1 text-gray-300 transition hover:bg-red-50 hover:text-red-500"
                            title="Ocultar widget"
                        >
                            <EyeOff className="h-3.5 w-3.5" />
                        </button>
                    )}
                </div>
            </div>

            <div className="flex-1">
                {loading || !payload ? <WidgetSkeleton /> : <WidgetRenderer meta={meta} payload={payload} onRetry={load} />}
            </div>
        </div>
    );
}
