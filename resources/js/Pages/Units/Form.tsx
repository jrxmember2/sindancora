import { Link } from '@inertiajs/react';

interface Block { id: string; name: string }

interface UnitFormData {
    number: string; block_id: string; floor: string; type: string;
    area_m2: string; fraction: string; status: string;
}

interface Props {
    data: UnitFormData;
    setData: (key: keyof UnitFormData, value: string) => void;
    errors: Partial<Record<keyof UnitFormData, string>>;
    processing: boolean;
    onSubmit: () => void;
    condominium: { id: string; name: string };
    blocks: Block[];
    typeLabels: Record<string, string>;
    statusLabels: Record<string, string>;
    submitLabel: string;
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
            {children}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

function Input(props: React.InputHTMLAttributes<HTMLInputElement>) {
    return <input className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" {...props} />;
}

function Select({ value, onChange, children }: { value: string; onChange: (v: string) => void; children: React.ReactNode }) {
    return (
        <select value={value} onChange={e => onChange(e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
            {children}
        </select>
    );
}

export default function UnitForm({ data, setData, errors, processing, onSubmit, condominium, blocks, typeLabels, statusLabels, submitLabel }: Props) {
    return (
        <div className="mx-auto max-w-xl space-y-6">
            <div className="rounded-xl bg-white border border-gray-100 shadow-sm p-6 space-y-5">
                <div className="grid grid-cols-2 gap-4">
                    <Field label="Número *" error={errors.number}>
                        <Input value={data.number} onChange={e => setData('number', e.target.value)} placeholder="101, A-01…" />
                    </Field>
                    <Field label="Bloco" error={errors.block_id}>
                        <Select value={data.block_id} onChange={v => setData('block_id', v)}>
                            <option value="">Sem bloco</option>
                            {blocks.map(b => <option key={b.id} value={b.id}>{b.name}</option>)}
                        </Select>
                    </Field>
                </div>
                <div className="grid grid-cols-2 gap-4">
                    <Field label="Tipo *" error={errors.type}>
                        <Select value={data.type} onChange={v => setData('type', v)}>
                            {Object.entries(typeLabels).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                        </Select>
                    </Field>
                    <Field label="Status *" error={errors.status}>
                        <Select value={data.status} onChange={v => setData('status', v)}>
                            {Object.entries(statusLabels).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                        </Select>
                    </Field>
                </div>
                <div className="grid grid-cols-3 gap-4">
                    <Field label="Andar" error={errors.floor}>
                        <Input type="number" value={data.floor} onChange={e => setData('floor', e.target.value)} placeholder="1" />
                    </Field>
                    <Field label="Área (m²)" error={errors.area_m2}>
                        <Input type="number" step="0.01" value={data.area_m2} onChange={e => setData('area_m2', e.target.value)} placeholder="0,00" />
                    </Field>
                    <Field label="Fração Ideal" error={errors.fraction}>
                        <Input type="number" step="0.000001" value={data.fraction} onChange={e => setData('fraction', e.target.value)} placeholder="0,000000" />
                    </Field>
                </div>
            </div>

            <div className="flex justify-between">
                <Link href={route('condominiums.units.index', condominium.id)} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancelar
                </Link>
                <button onClick={onSubmit} disabled={processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors">
                    {processing ? 'Salvando…' : submitLabel}
                </button>
            </div>
        </div>
    );
}
