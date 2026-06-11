import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';

interface Option { value: string; label: string }

export default function Create({ condominiums, types }: { condominiums: Option[]; types: Record<string, string> }) {
    const form = useForm({
        condominium_id: condominiums[0]?.value ?? '',
        type: 'found',
        title: '',
        description: '',
        category: '',
        location: '',
        occurred_on: '',
        photo: null as File | null,
    });

    const input = 'w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500';

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('lost-found.store'), { forceFormData: true });
    };

    return (
        <AppLayout>
            <Head title="Novo item" />
            <form onSubmit={submit} className="mx-auto max-w-2xl space-y-5">
                <h1 className="text-2xl font-bold text-gray-900">Achados & Perdidos — novo item</h1>

                <div className="space-y-4 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Condomínio</label>
                            <select value={form.data.condominium_id} onChange={(e) => form.setData('condominium_id', e.target.value)} className={input}>
                                {condominiums.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                            </select>
                            {form.errors.condominium_id && <p className="mt-1 text-xs text-red-600">{form.errors.condominium_id}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Tipo</label>
                            <select value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} className={input}>
                                {Object.entries(types).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                            </select>
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Título</label>
                        <input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} className={input} placeholder="Ex.: Molho de chaves, guarda-chuva azul" />
                        {form.errors.title && <p className="mt-1 text-xs text-red-600">{form.errors.title}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Descrição (opcional)</label>
                        <textarea value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} rows={2} className={input} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Categoria</label>
                            <input value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} className={input} placeholder="Opcional" />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Local</label>
                            <input value={form.data.location} onChange={(e) => form.setData('location', e.target.value)} className={input} placeholder="Opcional" />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Data</label>
                            <input type="date" value={form.data.occurred_on} onChange={(e) => form.setData('occurred_on', e.target.value)} className={input} />
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Foto (opcional)</label>
                        <input type="file" accept="image/png,image/jpeg,image/webp" onChange={(e) => form.setData('photo', e.target.files?.[0] ?? null)} className="block w-full text-sm" />
                        {form.errors.photo && <p className="mt-1 text-xs text-red-600">{form.errors.photo}</p>}
                    </div>
                </div>

                <button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    {form.processing ? 'Salvando…' : 'Registrar item'}
                </button>
            </form>
        </AppLayout>
    );
}
