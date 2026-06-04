import { Link } from '@inertiajs/react';
import AttachmentInput from '@/Components/AttachmentInput';
import AttachmentList, { Attachment } from '@/Components/AttachmentList';

export interface CommonAreaFormData {
    condominium_id: string;
    name: string;
    description: string;
    capacity: string;
    requires_approval: boolean;
    min_advance_days: string;
    opening_time: string;
    closing_time: string;
    fee: string;
    deposit: string;
    rules: string;
    active: boolean;
}

interface Option { value: string; label: string }

interface Props {
    data: CommonAreaFormData;
    setData: (key: keyof CommonAreaFormData, value: string | boolean) => void;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    onSubmit: () => void;
    condominiums: Option[];
    submitLabel: string;
    backHref: string;
    photos: File[];
    onPhotosChange: (files: File[]) => void;
    existingPhotos?: Attachment[];
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

export default function CommonAreaForm({ data, setData, errors, processing, onSubmit, condominiums, submitLabel, backHref, photos, onPhotosChange, existingPhotos = [] }: Props) {
    return (
        <div className="mx-auto max-w-2xl space-y-6">
            <div className="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <div className="grid grid-cols-2 gap-4">
                    <Field label="Condomínio *" error={errors.condominium_id}>
                        <select value={data.condominium_id} onChange={e => setData('condominium_id', e.target.value)} className={inputClass}>
                            <option value="">Selecione…</option>
                            {condominiums.map(c => <option key={c.value} value={c.value}>{c.label}</option>)}
                        </select>
                    </Field>
                    <Field label="Capacidade (pessoas)" error={errors.capacity}>
                        <input type="number" min={1} value={data.capacity} onChange={e => setData('capacity', e.target.value)} className={inputClass} />
                    </Field>
                </div>

                <Field label="Nome *" error={errors.name}>
                    <input value={data.name} onChange={e => setData('name', e.target.value)} className={inputClass} maxLength={150} />
                </Field>

                <Field label="Descrição" error={errors.description}>
                    <textarea value={data.description} onChange={e => setData('description', e.target.value)} rows={2} className={`${inputClass} resize-none`} />
                </Field>

                <div className="grid grid-cols-3 gap-4">
                    <Field label="Abertura" error={errors.opening_time}>
                        <input type="time" value={data.opening_time} onChange={e => setData('opening_time', e.target.value)} className={inputClass} />
                    </Field>
                    <Field label="Fechamento" error={errors.closing_time}>
                        <input type="time" value={data.closing_time} onChange={e => setData('closing_time', e.target.value)} className={inputClass} />
                    </Field>
                    <Field label="Antecedência mín. (dias) *" error={errors.min_advance_days}>
                        <input type="number" min={0} value={data.min_advance_days} onChange={e => setData('min_advance_days', e.target.value)} className={inputClass} />
                    </Field>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <Field label="Taxa de uso (R$)" error={errors.fee}>
                        <input type="number" min={0} step="0.01" value={data.fee} onChange={e => setData('fee', e.target.value)} className={inputClass} />
                    </Field>
                    <Field label="Caução (R$)" error={errors.deposit}>
                        <input type="number" min={0} step="0.01" value={data.deposit} onChange={e => setData('deposit', e.target.value)} className={inputClass} />
                    </Field>
                </div>

                <Field label="Regras de uso" error={errors.rules}>
                    <textarea value={data.rules} onChange={e => setData('rules', e.target.value)} rows={3} className={`${inputClass} resize-none`} />
                </Field>

                {existingPhotos.length > 0 && (
                    <div>
                        <p className="mb-1 block text-sm font-medium text-gray-700">Fotos atuais</p>
                        <AttachmentList attachments={existingPhotos} canRemove />
                    </div>
                )}

                <AttachmentInput
                    value={photos}
                    onChange={onPhotosChange}
                    error={errors.photos}
                    label="Fotos da área"
                    hint="Imagens que ajudam o morador a conhecer o espaço."
                />

                <div className="flex flex-col gap-3 border-t border-gray-100 pt-4">
                    <label className="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" checked={data.requires_approval} onChange={e => setData('requires_approval', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                        Exige aprovação do síndico
                    </label>
                    <label className="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" checked={data.active} onChange={e => setData('active', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                        Ativa (disponível para reserva)
                    </label>
                </div>
            </div>

            <div className="flex items-center justify-between">
                <Link href={backHref} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">Cancelar</Link>
                <button onClick={onSubmit} disabled={processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50">
                    {processing ? 'Salvando…' : submitLabel}
                </button>
            </div>
        </div>
    );
}
