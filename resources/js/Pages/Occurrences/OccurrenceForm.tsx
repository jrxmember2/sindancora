import { Link } from '@inertiajs/react';
import AttachmentInput from '@/Components/AttachmentInput';

export interface OccurrenceFormData {
    condominium_id: string;
    unit_id: string;
    assigned_to: string;
    title: string;
    description: string;
    category: string;
    priority: string;
}

interface Option { value: string; label: string }
interface UnitOption extends Option { condominium_id: string }

interface Props {
    data: OccurrenceFormData;
    setData: (key: keyof OccurrenceFormData, value: string) => void;
    errors: Partial<Record<keyof OccurrenceFormData, string>> & { attachments?: string };
    processing: boolean;
    onSubmit: () => void;
    condominiums: Option[];
    units: UnitOption[];
    assignableUsers: Option[];
    categories: Record<string, string>;
    priorities: Record<string, string>;
    submitLabel: string;
    backHref: string;
    attachments?: File[];
    onAttachmentsChange?: (files: File[]) => void;
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

export default function OccurrenceForm({
    data, setData, errors, processing, onSubmit, condominiums, units, assignableUsers, categories, priorities, submitLabel, backHref,
    attachments, onAttachmentsChange,
}: Props) {
    const condoUnits = units.filter(u => u.condominium_id === data.condominium_id);

    return (
        <div className="mx-auto max-w-2xl space-y-6">
            <div className="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <div className="grid grid-cols-2 gap-4">
                    <Field label="Condomínio *" error={errors.condominium_id}>
                        <select
                            value={data.condominium_id}
                            onChange={e => { setData('condominium_id', e.target.value); setData('unit_id', ''); }}
                            className={inputClass}
                        >
                            <option value="">Selecione…</option>
                            {condominiums.map(c => <option key={c.value} value={c.value}>{c.label}</option>)}
                        </select>
                    </Field>
                    <Field label="Unidade" error={errors.unit_id}>
                        <select value={data.unit_id} onChange={e => setData('unit_id', e.target.value)} className={inputClass} disabled={!data.condominium_id}>
                            <option value="">Sem unidade específica</option>
                            {condoUnits.map(u => <option key={u.value} value={u.value}>{u.label}</option>)}
                        </select>
                    </Field>
                </div>

                <Field label="Título *" error={errors.title}>
                    <input value={data.title} onChange={e => setData('title', e.target.value)} className={inputClass} maxLength={200} />
                </Field>

                <div className="grid grid-cols-2 gap-4">
                    <Field label="Categoria *" error={errors.category}>
                        <select value={data.category} onChange={e => setData('category', e.target.value)} className={inputClass}>
                            {Object.entries(categories).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                    </Field>
                    <Field label="Prioridade *" error={errors.priority}>
                        <select value={data.priority} onChange={e => setData('priority', e.target.value)} className={inputClass}>
                            {Object.entries(priorities).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                    </Field>
                </div>

                <Field label="Responsável" error={errors.assigned_to}>
                    <select value={data.assigned_to} onChange={e => setData('assigned_to', e.target.value)} className={inputClass}>
                        <option value="">Não atribuído</option>
                        {assignableUsers.map(u => <option key={u.value} value={u.value}>{u.label}</option>)}
                    </select>
                </Field>

                <Field label="Descrição *" error={errors.description}>
                    <textarea
                        value={data.description}
                        onChange={e => setData('description', e.target.value)}
                        rows={5}
                        className={`${inputClass} resize-none`}
                        maxLength={5000}
                    />
                </Field>

                {onAttachmentsChange && (
                    <AttachmentInput
                        value={attachments ?? []}
                        onChange={onAttachmentsChange}
                        error={errors.attachments}
                        label="Anexos (fotos, documentos)"
                        hint="Anexe fotos do problema, se ajudar. Até 50 MB por arquivo."
                    />
                )}
            </div>

            <div className="flex items-center justify-between">
                <Link href={backHref} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                    Cancelar
                </Link>
                <button onClick={onSubmit} disabled={processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50">
                    {processing ? 'Salvando…' : submitLabel}
                </button>
            </div>
        </div>
    );
}
