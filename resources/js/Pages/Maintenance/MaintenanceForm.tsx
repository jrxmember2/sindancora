import { Link } from '@inertiajs/react';

export interface MaintenanceFormData {
    condominium_id: string;
    supplier_id: string;
    category: string;
    title: string;
    description: string;
    frequency: string;
    next_due_date: string;
    alert_days: string;
    is_active: boolean;
}

interface Option { value: string; label: string }

interface Props {
    data: MaintenanceFormData;
    setData: (key: keyof MaintenanceFormData, value: string | boolean) => void;
    errors: Partial<Record<keyof MaintenanceFormData, string>>;
    processing: boolean;
    onSubmit: () => void;
    submitLabel: string;
    backHref: string;
    categories: Record<string, string>;
    frequencies: Record<string, string>;
    condominiums: Option[];
    suppliers: Option[];
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
            {children}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

const inputClass = 'w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';

export default function MaintenanceForm({ data, setData, errors, processing, onSubmit, submitLabel, backHref, categories, frequencies, condominiums, suppliers }: Props) {
    return (
        <div className="mx-auto max-w-2xl space-y-6">
            <div className="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <Field label="Título *" error={errors.title}>
                    <input value={data.title} onChange={e => setData('title', e.target.value)} className={inputClass} maxLength={150} placeholder="Ex.: Manutenção do elevador" />
                </Field>

                <div className="grid grid-cols-2 gap-4">
                    <Field label="Condomínio *" error={errors.condominium_id}>
                        <select value={data.condominium_id} onChange={e => setData('condominium_id', e.target.value)} className={inputClass}>
                            <option value="">Selecione…</option>
                            {condominiums.map(c => <option key={c.value} value={c.value}>{c.label}</option>)}
                        </select>
                    </Field>
                    <Field label="Categoria" error={errors.category}>
                        <select value={data.category} onChange={e => setData('category', e.target.value)} className={inputClass}>
                            <option value="">—</option>
                            {Object.entries(categories).map(([slug, label]) => <option key={slug} value={slug}>{label}</option>)}
                        </select>
                    </Field>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <Field label="Fornecedor padrão" error={errors.supplier_id}>
                        <select value={data.supplier_id} onChange={e => setData('supplier_id', e.target.value)} className={inputClass}>
                            <option value="">—</option>
                            {suppliers.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
                        </select>
                    </Field>
                    <Field label="Recorrência *" error={errors.frequency}>
                        <select value={data.frequency} onChange={e => setData('frequency', e.target.value)} className={inputClass}>
                            {Object.entries(frequencies).map(([slug, label]) => <option key={slug} value={slug}>{label}</option>)}
                        </select>
                    </Field>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <Field label="Próxima data prevista *" error={errors.next_due_date}>
                        <input type="date" value={data.next_due_date} onChange={e => setData('next_due_date', e.target.value)} className={inputClass} />
                    </Field>
                    <Field label="Alerta (dias de antecedência)" error={errors.alert_days}>
                        <input type="number" min={0} max={365} value={data.alert_days} onChange={e => setData('alert_days', e.target.value)} className={inputClass} />
                    </Field>
                </div>

                <Field label="Descrição / observações" error={errors.description}>
                    <textarea value={data.description} onChange={e => setData('description', e.target.value)} rows={3} className={`${inputClass} resize-none`} />
                </Field>

                <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" checked={data.is_active} onChange={e => setData('is_active', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                    Manutenção ativa (gera alertas)
                </label>
            </div>

            <div className="flex justify-between">
                <Link href={backHref} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">Cancelar</Link>
                <button onClick={onSubmit} disabled={processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50">
                    {processing ? 'Salvando…' : submitLabel}
                </button>
            </div>
        </div>
    );
}
