import { Link } from '@inertiajs/react';

export interface Option { value: string; label: string }

export interface QuotationFormData {
    condominium_id: string;
    category: string;
    title: string;
    description: string;
    status: string;
    response_deadline: string;
    notes: string;
}

interface Props {
    data: QuotationFormData;
    setData: (key: keyof QuotationFormData, value: string) => void;
    errors: Partial<Record<keyof QuotationFormData, string>>;
    processing: boolean;
    onSubmit: () => void;
    submitLabel: string;
    backHref: string;
    condominiums: Option[];
    categories: Record<string, string>;
    statuses: Record<string, string>;
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

export default function QuotationForm({
    data,
    setData,
    errors,
    processing,
    onSubmit,
    submitLabel,
    backHref,
    condominiums,
    categories,
    statuses,
}: Props) {
    return (
        <div className="mx-auto max-w-3xl space-y-6">
            <div className="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <Field label="Título *" error={errors.title}>
                    <input value={data.title} onChange={(e) => setData('title', e.target.value)} className={inputClass} maxLength={150} placeholder="Ex.: Pintura da fachada" />
                </Field>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Field label="Condomínio *" error={errors.condominium_id}>
                        <select value={data.condominium_id} onChange={(e) => setData('condominium_id', e.target.value)} className={inputClass}>
                            <option value="">Selecione...</option>
                            {condominiums.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                        </select>
                    </Field>
                    <Field label="Categoria" error={errors.category}>
                        <select value={data.category} onChange={(e) => setData('category', e.target.value)} className={inputClass}>
                            <option value="">Sem categoria</option>
                            {Object.entries(categories).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                    </Field>
                    <Field label="Status" error={errors.status}>
                        <select value={data.status} onChange={(e) => setData('status', e.target.value)} className={inputClass}>
                            {Object.entries(statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                    </Field>
                </div>

                <Field label="Prazo para receber propostas" error={errors.response_deadline}>
                    <input type="date" value={data.response_deadline} onChange={(e) => setData('response_deadline', e.target.value)} className={inputClass} />
                </Field>

                <Field label="Descrição do serviço/produto" error={errors.description}>
                    <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={5} className={`${inputClass} resize-none`} placeholder="Descreva escopo, local, requisitos técnicos e critérios de comparação." />
                </Field>

                <Field label="Observações internas" error={errors.notes}>
                    <textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} rows={3} className={`${inputClass} resize-none`} />
                </Field>
            </div>

            <div className="flex justify-between">
                <Link href={backHref} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</Link>
                <button type="button" onClick={onSubmit} disabled={processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    {processing ? 'Salvando...' : submitLabel}
                </button>
            </div>
        </div>
    );
}
