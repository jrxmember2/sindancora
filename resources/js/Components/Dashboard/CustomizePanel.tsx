import { useEffect, useState } from 'react';
import { Eye, EyeOff, GripVertical, X } from 'lucide-react';
import { color, moduleColor } from '@/lib/dashboardTheme';
import type { WidgetMeta } from './types';

/**
 * Drawer de personalização: ocultar/mostrar e reordenar widgets por arrastar e
 * soltar (HTML5 drag-and-drop nativo, sem dependência extra). A ordem é salva
 * em `widget_order` no backend.
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
    const [dragKey, setDragKey] = useState<string | null>(null);
    const [overKey, setOverKey] = useState<string | null>(null);

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

    const reorder = (fromKey: string, toKey: string) => {
        if (fromKey === toKey) return;
        setItems((prev) => {
            const from = prev.findIndex((i) => i.key === fromKey);
            const to = prev.findIndex((i) => i.key === toKey);
            if (from === -1 || to === -1) return prev;
            const next = [...prev];
            const [moved] = next.splice(from, 1);
            next.splice(to, 0, moved);
            return next;
        });
    };

    const toggle = (key: string) => {
        setHiddenSet((prev) => {
            const next = new Set(prev);
            next.has(key) ? next.delete(key) : next.add(key);
            return next;
        });
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
                        <p className="text-xs text-gray-500">Arraste para reordenar · clique no olho para ocultar.</p>
                    </div>
                    <button onClick={onClose} className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100">
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <div className="flex-1 overflow-y-auto p-4">
                    <ul className="space-y-2">
                        {items.map((item) => {
                            const isHidden = hiddenSet.has(item.key);
                            const mc = color(moduleColor(item.module));
                            const isDragging = dragKey === item.key;
                            const isOver = overKey === item.key && dragKey !== item.key;
                            return (
                                <li
                                    key={item.key}
                                    draggable
                                    onDragStart={(e) => {
                                        setDragKey(item.key);
                                        e.dataTransfer.effectAllowed = 'move';
                                    }}
                                    onDragEnter={() => setOverKey(item.key)}
                                    onDragOver={(e) => {
                                        e.preventDefault();
                                        if (dragKey) reorder(dragKey, item.key);
                                    }}
                                    onDragEnd={() => {
                                        setDragKey(null);
                                        setOverKey(null);
                                    }}
                                    onDrop={(e) => {
                                        e.preventDefault();
                                        setDragKey(null);
                                        setOverKey(null);
                                    }}
                                    className={`flex items-center gap-2 rounded-xl border p-3 transition ${
                                        isHidden ? 'border-gray-100 bg-gray-50 opacity-60' : 'border-gray-200 bg-white'
                                    } ${isDragging ? 'opacity-40 ring-2 ring-blue-300' : ''} ${
                                        isOver ? 'border-blue-300 ring-1 ring-blue-200' : ''
                                    }`}
                                >
                                    <span
                                        className="flex-shrink-0 cursor-grab touch-none text-gray-300 transition hover:text-gray-500 active:cursor-grabbing"
                                        title="Arraste para reordenar"
                                    >
                                        <GripVertical className="h-5 w-5" />
                                    </span>
                                    <span className={`h-2.5 w-2.5 flex-shrink-0 rounded-full ${mc.solid}`} />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-gray-800">{item.name}</p>
                                        <p className="truncate text-xs text-gray-400">{item.description}</p>
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
