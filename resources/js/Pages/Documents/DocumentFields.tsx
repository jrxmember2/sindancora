interface Option { value: string; label: string }

export interface DocumentMeta {
    condominium_id: string;
    title: string;
    description: string;
    category: string;
    visibility: string;
}

interface Props {
    data: DocumentMeta;
    setData: (key: keyof DocumentMeta, value: string) => void;
    errors: Partial<Record<string, string>>;
    condominiums: Option[];
    categories: Record<string, string>;
    visibilities: Record<string, string>;
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

export default function DocumentFields({ data, setData, errors, condominiums, categories, visibilities }: Props) {
    return (
        <>
            <Field label="Condomínio *" error={errors.condominium_id}>
                <select value={data.condominium_id} onChange={e => setData('condominium_id', e.target.value)} className={inputClass}>
                    <option value="">Selecione…</option>
                    {condominiums.map(c => <option key={c.value} value={c.value}>{c.label}</option>)}
                </select>
            </Field>

            <Field label="Título *" error={errors.title}>
                <input value={data.title} onChange={e => setData('title', e.target.value)} className={inputClass} maxLength={200} />
            </Field>

            <div className="grid grid-cols-2 gap-4">
                <Field label="Categoria *" error={errors.category}>
                    <select value={data.category} onChange={e => setData('category', e.target.value)} className={inputClass}>
                        {Object.entries(categories).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                </Field>
                <Field label="Visibilidade *" error={errors.visibility}>
                    <select value={data.visibility} onChange={e => setData('visibility', e.target.value)} className={inputClass}>
                        {Object.entries(visibilities).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                </Field>
            </div>

            <Field label="Descrição" error={errors.description}>
                <textarea value={data.description} onChange={e => setData('description', e.target.value)} rows={3} className={`${inputClass} resize-none`} maxLength={2000} />
            </Field>
        </>
    );
}
