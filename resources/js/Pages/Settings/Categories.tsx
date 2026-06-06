import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { Tags, Plus, Trash2, Pencil, X } from 'lucide-react';

interface Category {
    id: string;
    type: string;
    name: string;
    slug: string;
    color: string | null;
    sort_order: number;
    is_active: boolean;
}
interface Props {
    categories: Category[];
    types: Record<string, string>;
    defaults: Record<string, Record<string, string>>;
}

export default function Categories({ categories, types, defaults }: Props) {
    const [editing, setEditing] = useState<Category | { type: string } | null>(null);

    const remove = (c: Category) => {
        if (confirm(`Remover a categoria "${c.name}"? Os registros já classificados são preservados.`)) {
            router.delete(route('categories.destroy', c.id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout>
            <Head title="Categorias" />

            <div className="mx-auto max-w-3xl space-y-8">
                <div>
                    <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900">
                        <Tags className="h-6 w-6 text-blue-600" /> Categorias
                    </h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Crie categorias próprias para classificar ocorrências e documentos, além das categorias padrão do sistema.
                    </p>
                </div>

                {Object.entries(types).map(([type, label]) => {
                    const custom = categories.filter((c) => c.type === type);
                    const base = defaults[type] ?? {};
                    return (
                        <section key={type} className="space-y-3">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-semibold text-gray-800">{label}</h2>
                                <button onClick={() => setEditing({ type })} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                                    <Plus className="h-4 w-4" /> Nova categoria
                                </button>
                            </div>

                            {/* Padrão (somente leitura) */}
                            <div className="flex flex-wrap gap-2">
                                {Object.values(base).map((name) => (
                                    <span key={name} className="rounded-full bg-gray-100 px-3 py-1 text-xs text-gray-500">{name} · padrão</span>
                                ))}
                            </div>

                            {/* Customizadas */}
                            {custom.length > 0 && (
                                <ul className="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white">
                                    {custom.map((c) => (
                                        <li key={c.id} className="flex items-center gap-3 px-4 py-3">
                                            <span className="inline-block h-3 w-3 rounded-full" style={{ backgroundColor: c.color ?? '#9ca3af' }} />
                                            <span className="font-medium text-gray-900">{c.name}</span>
                                            {!c.is_active && <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">inativa</span>}
                                            <span className="ml-auto text-xs text-gray-400">ordem {c.sort_order}</span>
                                            <button onClick={() => setEditing(c)} className="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700"><Pencil className="h-4 w-4" /></button>
                                            <button onClick={() => remove(c)} className="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600"><Trash2 className="h-4 w-4" /></button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    );
                })}
            </div>

            {editing && (
                <CategoryForm
                    category={'id' in editing ? editing : null}
                    type={'id' in editing ? editing.type : editing.type}
                    typeLabel={types['id' in editing ? editing.type : editing.type]}
                    onClose={() => setEditing(null)}
                />
            )}
        </AppLayout>
    );
}

function CategoryForm({ category, type, typeLabel, onClose }: { category: Category | null; type: string; typeLabel: string; onClose: () => void }) {
    const form = useForm({
        type,
        name: category?.name ?? '',
        color: category?.color ?? '#3b82f6',
        sort_order: category?.sort_order ?? 0,
        is_active: category?.is_active ?? true,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: onClose };
        if (category) form.put(route('categories.update', category.id), opts);
        else form.post(route('categories.store'), opts);
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
            <div className="w-full max-w-md rounded-xl bg-white p-6" onClick={(e) => e.stopPropagation()}>
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="font-semibold text-gray-900">{category ? 'Editar categoria' : 'Nova categoria'} · {typeLabel}</h2>
                    <button onClick={onClose} className="rounded p-1 text-gray-400 hover:bg-gray-100"><X className="h-4 w-4" /></button>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Nome</label>
                        <input type="text" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} maxLength={60} placeholder="Ex.: Jardinagem" className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" />
                        {form.errors.name && <p className="mt-1 text-xs text-red-600">{form.errors.name}</p>}
                    </div>

                    <div className="flex gap-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Cor</label>
                            <input type="color" value={form.data.color} onChange={(e) => form.setData('color', e.target.value)} className="h-9 w-14 cursor-pointer rounded-lg border border-gray-300" />
                        </div>
                        <div className="w-24">
                            <label className="mb-1 block text-sm font-medium text-gray-700">Ordem</label>
                            <input type="number" min={0} max={999} value={form.data.sort_order} onChange={(e) => form.setData('sort_order', Number(e.target.value))} className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" />
                        </div>
                        <label className="flex items-end gap-2 pb-2 text-sm text-gray-700">
                            <input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                            Ativa
                        </label>
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" onClick={onClose} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                        <button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    );
}
