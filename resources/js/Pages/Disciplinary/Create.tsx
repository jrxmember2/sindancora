import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';

interface Option { value: string; label: string }
interface UnitOption extends Option { condominium_id: string; people: Option[] }

export default function Create({ condominiums, units, types, canGenerateCharge }: { condominiums: Option[]; units: UnitOption[]; types: Record<string, string>; canGenerateCharge: boolean }) {
    const firstCondominium = condominiums[0]?.value ?? '';
    const firstUnit = units.find((u) => u.condominium_id === firstCondominium)?.value ?? '';
    const form = useForm({
        condominium_id: firstCondominium,
        unit_id: firstUnit,
        person_id: '',
        type: 'warning',
        title: '',
        rule_reference: '',
        description: '',
        occurred_on: '',
        amount: '',
        due_date: '',
        generate_charge: false,
        attachments: [] as File[],
    });

    const input = 'w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500';
    const currentUnits = units.filter((unit) => unit.condominium_id === form.data.condominium_id);
    const selectedUnit = currentUnits.find((unit) => unit.value === form.data.unit_id);

    const changeCondominium = (value: string) => {
        const unit = units.find((item) => item.condominium_id === value);
        form.setData({ ...form.data, condominium_id: value, unit_id: unit?.value ?? '', person_id: '' });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('disciplinary.store'), { forceFormData: true });
    };

    return (
        <AppLayout>
            <Head title="Novo registro regimental" />
            <form onSubmit={submit} className="mx-auto max-w-3xl space-y-5">
                <h1 className="text-2xl font-bold text-gray-900">Novo registro regimental</h1>

                <div className="space-y-4 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Condominio</label>
                            <select value={form.data.condominium_id} onChange={(e) => changeCondominium(e.target.value)} className={input}>
                                {condominiums.map((condominium) => <option key={condominium.value} value={condominium.value}>{condominium.label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Unidade</label>
                            <select value={form.data.unit_id} onChange={(e) => form.setData({ ...form.data, unit_id: e.target.value, person_id: '' })} className={input}>
                                {currentUnits.map((unit) => <option key={unit.value} value={unit.value}>{unit.label}</option>)}
                            </select>
                            {form.errors.unit_id && <p className="mt-1 text-xs text-red-600">{form.errors.unit_id}</p>}
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Tipo</label>
                            <select value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} className={input}>
                                {Object.entries(types).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Pessoa vinculada</label>
                            <select value={form.data.person_id} onChange={(e) => form.setData('person_id', e.target.value)} className={input}>
                                <option value="">Responsavel principal</option>
                                {selectedUnit?.people.map((person) => <option key={person.value} value={person.value}>{person.label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Data do fato</label>
                            <input type="date" value={form.data.occurred_on} onChange={(e) => form.setData('occurred_on', e.target.value)} className={input} />
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Titulo</label>
                        <input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} className={input} placeholder="Ex.: Barulho fora do horario permitido" />
                        {form.errors.title && <p className="mt-1 text-xs text-red-600">{form.errors.title}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Referencia do regimento</label>
                        <input value={form.data.rule_reference} onChange={(e) => form.setData('rule_reference', e.target.value)} className={input} placeholder="Ex.: Art. 18, inciso II" />
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Descricao</label>
                        <textarea value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} rows={5} className={input} />
                        {form.errors.description && <p className="mt-1 text-xs text-red-600">{form.errors.description}</p>}
                    </div>

                    {form.data.type === 'fine' && (
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Valor</label>
                                <input type="number" min="0" step="0.01" value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} className={input} />
                                {form.errors.amount && <p className="mt-1 text-xs text-red-600">{form.errors.amount}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Vencimento</label>
                                <input type="date" value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} className={input} />
                            </div>
                            {canGenerateCharge && (
                                <label className="flex items-end gap-2 pb-2 text-sm text-gray-700">
                                    <input type="checkbox" checked={form.data.generate_charge} onChange={(e) => form.setData('generate_charge', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                    Gerar cobranca
                                </label>
                            )}
                        </div>
                    )}

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Anexos/evidencias</label>
                        <input type="file" multiple onChange={(e) => form.setData('attachments', Array.from(e.target.files ?? []))} className="block w-full text-sm" />
                        {form.errors.attachments && <p className="mt-1 text-xs text-red-600">{form.errors.attachments}</p>}
                    </div>
                </div>

                <button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    {form.processing ? 'Emitindo...' : 'Emitir registro'}
                </button>
            </form>
        </AppLayout>
    );
}
