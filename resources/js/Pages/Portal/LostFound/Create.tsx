import PortalLayout from '@/Layouts/PortalLayout';
import { Head, useForm } from '@inertiajs/react';

interface Option { value: string; label: string }

export default function Create({ condominiums, types }: { condominiums: Option[]; types: Record<string, string> }) {
    const form = useForm({
        condominium_id: condominiums[0]?.value ?? '',
        type: 'lost',
        title: '',
        description: '',
        location: '',
        occurred_on: '',
        photo: null as File | null,
    });

    const input = 'w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500';

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('portal.lost-found.store'), { forceFormData: true });
    };

    return (
        <PortalLayout title="Reportar item">
            <Head title="Reportar item" />
            <form onSubmit={submit} className="space-y-4">
                <div className="space-y-4 rounded-xl border border-gray-100 bg-white p-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        {condominiums.length > 1 && (
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Condomínio</label>
                                <select value={form.data.condominium_id} onChange={(e) => form.setData('condominium_id', e.target.value)} className={input}>
                                    {condominiums.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                                </select>
                            </div>
                        )}
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Tipo</label>
                            <select value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} className={input}>
                                {Object.entries(types).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                            </select>
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">O que é?</label>
                        <input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} className={input} placeholder="Ex.: Carteira preta, óculos de sol" />
                        {form.errors.title && <p className="mt-1 text-xs text-red-600">{form.errors.title}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Detalhes (opcional)</label>
                        <textarea value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} rows={2} className={input} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Onde / local (opcional)</label>
                            <input value={form.data.location} onChange={(e) => form.setData('location', e.target.value)} className={input} />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Data (opcional)</label>
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
                    {form.processing ? 'Enviando…' : 'Enviar'}
                </button>
            </form>
        </PortalLayout>
    );
}
