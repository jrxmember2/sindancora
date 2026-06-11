import { useEffect, useState } from 'react';
import { ArrowDown, ArrowUp, Eye, EyeOff, X } from 'lucide-react';
import { color, moduleColor } from '@/lib/dashboardTheme';
import type { WidgetMeta } from './types';

/**
 * Drawer de personalização: ocultar/mostrar e reordenar widgets. O drag-and-drop
 * visual fica para a fase 2 — aqui usamos setas para reordenar, já persistido.
 */
export default function CustomizePanel({
    open,
    meta,
    hidden,
    order,
    onClose,
    onSave,
}: {
    open: boolean;
    meta: WidgetMeta[];
    hidden: string[];
    order: string[];
    onClose: () => void;
    onSave: (hidden: string[], order: string[]) => void;
}) {
    const [items, setItems] = useState<WidgetMeta[]>([]);
    const [hiddenSet, setHiddenSet] = useState<Set<string>>(new Set());

    useEffect(() => {
        if (!open) return;
        // Ordena conforme a ordem salva; resto mantém ordem padrão.
        const rank = new Map(order.map((k, i) => [k, i]));
        const sorted = [...meta].sort(
            (a, b) => (rank.get(a.key) ?? 999) - (rank.get(b.key) ?? 999) || a.order - b.order,
        );
        setItems(sorted);
        setHiddenSet(new Set(hidden));
    }, [open, meta, hidden, order]);

    const move = (idx: number, dir: -1 | 1) => {
        const next = [...items];
        const target = idx + dir;
        if (target < 0 || target >= next.length) return;
        [next[idx], next[target]] = [next[target], next[idx]];
        setItems(next);
    };

    const toggle = (key: string) => {
        const next = new Set(hiddenSet);
        next.has(key) ? next.delete(key) : next.add(key);
        setHiddenSet(next);
    };

    const save = () => onSave([...hiddenSet], items.map((i) => i.key));

    if (!open) return null;

    return (
        <>
            <div className="fixed inset-0 z-40 bg-black/30" onClick={onClose} />
            <aside className="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col bg-white shadow-xl">
                <div className="flex items-center justify-between border-b px-5 py-4">
                    <div>
                        <h2 className="text-base font-semibold text-gray-900">Personalizar dashboard</h2>
                        <p className="text-xs text-gray-500">Mostre, oculte e reordene seus indicadores.</p>
                    </div>
                    <button onClick={onClose} className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100">
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <div className="flex-1 overflow-y-auto p-4">
                    <ul className="space-y-2">
                        {items.map((item, idx) => {
                            const isHidden = hiddenSet.has(item.key);
                            const mc = color(moduleColor(item.module));
                            return (
                                <li
                                    key={item.key}
                                    className={`flex items-center gap-3 rounded-xl border p-3 transition ${
                                        isHidden ? 'border-gray-100 bg-gray-50 opacity-60' : 'border-gray-200 bg-white'
                                    }`}
                                >
                                    <span className={`h-2.5 w-2.5 flex-shrink-0 rounded-full ${mc.solid}`} />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-gray-800">{item.name}</p>
                                        <p className="truncate text-xs text-gray-400">{item.description}</p>
                                    </div>
                                    <div className="flex flex-shrink-0 flex-col">
                                        <button onClick={() => move(idx, -1)} className="rounded p-0.5 text-gray-300 hover:text-gray-600" title="Subir">
                                            <ArrowUp className="h-3.5 w-3.5" />
                                        </button>
                                        <button onClick={() => move(idx, 1)} className="rounded p-0.5 text-gray-300 hover:text-gray-600" title="Descer">
                                            <ArrowDown className="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                    <button
                                        onClick={() => toggle(item.key)}
                                        className={`flex-shrink-0 rounded-lg p-1.5 transition ${
                                            isHidden ? 'text-gray-400 hover:bg-gray-100' : 'text-blue-600 hover:bg-blue-50'
                                        }`}
                                        title={isHidden ? 'Mostrar' : 'Ocultar'}
                                    >
                                        {isHidden ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                </div>

                <div className="flex items-center justify-end gap-2 border-t px-5 py-4">
                    <button onClick={onClose} className="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">
                        Cancelar
                    </button>
                    <button onClick={save} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
                        Salvar layout
                    </button>
                </div>
            </aside>
        </>
    );
}
