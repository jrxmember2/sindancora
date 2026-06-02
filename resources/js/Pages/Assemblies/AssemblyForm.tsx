import { Link } from '@inertiajs/react';

export interface Option { value: string; label: string }

export interface AssemblyFormData {
    condominium_id: string;
    title: string;
    description: string;
    scheduled_at: string;
}

interface Props {
    data: AssemblyFormData;
    setData: (key: keyof AssemblyFormData, value: string) => void;
    errors: Partial<Record<keyof AssemblyFormData, string>>;
    processing: boolean;
    condominiums: Option[];
    onSubmit: (e: React.FormEvent) => void;
    submitLabel: string;
    cancelHref: string;
}

const field = 'mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';

export default function AssemblyForm({ data, setData, errors, processing, condominiums, onSubmit, submitLabel, cancelHref }: Props) {
    return (
        <form onSubmit={onSubmit} className="space-y-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <div>
                <label className="block text-sm font-medium text-gray-700">Condomínio *</label>
                <select value={data.condominium_id} onChange={(e) => setData('condominium_id', e.target.value)} className={field}>
                    <option value="">Selecione…</option>
                    {condominiums.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                </select>
                {errors.condominium_id && <p className="mt-1 text-xs text-red-600">{errors.condominium_id}</p>}
            </div>
            <div>
                <label className="block text-sm font-medium text-gray-700">Título *</label>
                <input value={data.title} onChange={(e) => setData('title', e.target.value)} className={field} placeholder="Ex.: Assembleia Geral Ordinária 2026" />
                {errors.title && <p className="mt-1 text-xs text-red-600">{errors.title}</p>}
            </div>
            <div>
                <label className="block text-sm font-medium text-gray-700">Data/hora</label>
                <input type="datetime-local" value={data.scheduled_at} onChange={(e) => setData('scheduled_at', e.target.value)} className={field} />
                {errors.scheduled_at && <p className="mt-1 text-xs text-red-600">{errors.scheduled_at}</p>}
            </div>
            <div>
                <label className="block text-sm font-medium text-gray-700">Descrição</label>
                <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={3} className={field} />
            </div>
            <div className="flex justify-end gap-2 pt-2">
                <Link href={cancelHref} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</Link>
                <button type="submit" disabled={processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    {processing ? 'Salvando…' : submitLabel}
                </button>
            </div>
        </form>
    );
}
