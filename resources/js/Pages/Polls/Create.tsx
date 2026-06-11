import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';

interface Option { value: string; label: string }

export default function Create({ condominiums }: { condominiums: Option[] }) {
    const form = useForm<{
        condominium_id: string;
        title: string;
        description: string;
        is_anonymous: boolean;
        closes_at: string;
        options: string[];
    }>({
        condominium_id: condominiums[0]?.value ?? '',
        title: '',
        description: '',
        is_anonymous: false,
        closes_at: '',
        options: ['', ''],
    });

    const input = 'w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500';

    const setOption = (i: number, v: string) => {
        const next = [...form.data.options];
        next[i] = v;
        form.setData('options', next);
    };
    const addOption = () => form.setData('options', [...form.data.options, '']);
    const removeOption = (i: number) => form.setData('options', form.data.options.filter((_, idx) => idx !== i));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('polls.store'));
    };

    return (
        <AppLayout>
            <Head title="Nova enquete" />
            <form onSubmit={submit} className="mx-auto max-w-2xl space-y-5">
                <h1 className="text-2xl font-bold text-gray-900">Nova enquete</h1>

                <div className="space-y-4 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Condomínio</label>
                        <select value={form.data.condominium_id} onChange={(e) => form.setData('condominium_id', e.target.value)} className={input}>
                            {condominiums.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                        </select>
                        {form.errors.condominium_id && <p className="mt-1 text-xs text-red-600">{form.errors.condominium_id}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Pergunta / título</label>
                        <input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} className={input} placeholder="Ex.: Qual horário preferem para a manutenção da piscina?" />
                        {form.errors.title && <p className="mt-1 text-xs text-red-600">{form.errors.title}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Descrição (opcional)</label>
                        <textarea value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} rows={2} className={input} />
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Opções</label>
                        <div className="space-y-2">
                            {form.data.options.map((opt, i) => (
                                <div key={i} className="flex items-center gap-2">
                                    <input value={opt} onChange={(e) => setOption(i, e.target.value)} className={input} placeholder={`Opção ${i + 1}`} />
                                    {form.data.options.length > 2 && (
                                        <button type="button" onClick={() => removeOption(i)} className="rounded p-2 text-gray-400 hover:text-red-600"><Trash2 className="h-4 w-4" /></button>
                                    )}
                                </div>
                            ))}
                        </div>
                        {form.errors.options && <p className="mt-1 text-xs text-red-600">{form.errors.options}</p>}
                        {form.data.options.length < 10 && (
                            <button type="button" onClick={addOption} className="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 hover:text-blue-700">
                                <Plus className="h-4 w-4" /> Adicionar opção
                            </button>
                        )}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Encerra em (opcional)</label>
                            <input type="datetime-local" value={form.data.closes_at} onChange={(e) => form.setData('closes_at', e.target.value)} className={input} />
                        </div>
                        <label className="flex items-center gap-2 pt-7 text-sm text-gray-700">
                            <input type="checkbox" checked={form.data.is_anonymous} onChange={(e) => form.setData('is_anonymous', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                            Enquete anônima
                        </label>
                    </div>
                </div>

                <button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    {form.processing ? 'Salvando…' : 'Criar enquete'}
                </button>
            </form>
        </AppLayout>
    );
}
