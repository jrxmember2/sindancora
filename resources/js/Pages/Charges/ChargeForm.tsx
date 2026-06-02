import { Link } from '@inertiajs/react';

export interface Option { value: string; label: string }
export interface UnitOption { value: string; label: string; condominium_id: string }

export interface ChargeFormData {
    condominium_id: string;
    unit_id: string;
    type: string;
    description: string;
    reference_month: string;
    amount: string;
    due_date: string;
    fine_rate: string;
    interest_rate: string;
    notes: string;
}

interface Props {
    data: ChargeFormData;
    setData: (key: keyof ChargeFormData, value: string) => void;
    errors: Partial<Record<keyof ChargeFormData, string>>;
    processing: boolean;
    condominiums: Option[];
    units: UnitOption[];
    types: Record<string, string>;
    onSubmit: (e: React.FormEvent) => void;
    submitLabel: string;
    cancelHref: string;
    lockFinancial?: boolean;
}

const field = 'mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';
const lockedField = `${field} bg-gray-50 text-gray-500`;

export default function ChargeForm({ data, setData, errors, processing, condominiums, units, types, onSubmit, submitLabel, cancelHref, lockFinancial = false }: Props) {
    const unitsOfCondo = units.filter((u) => u.condominium_id === data.condominium_id);

    return (
        <form onSubmit={onSubmit} className="space-y-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label className="block text-sm font-medium text-gray-700">Condomínio *</label>
                    <select value={data.condominium_id} onChange={(e) => { setData('condominium_id', e.target.value); setData('unit_id', ''); }} className={field}>
                        <option value="">Selecione…</option>
                        {condominiums.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                    </select>
                    {errors.condominium_id && <p className="mt-1 text-xs text-red-600">{errors.condominium_id}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Unidade *</label>
                    <select value={data.unit_id} onChange={(e) => setData('unit_id', e.target.value)} disabled={!data.condominium_id} className={field}>
                        <option value="">Selecione…</option>
                        {unitsOfCondo.map((u) => <option key={u.value} value={u.value}>{u.label}</option>)}
                    </select>
                    {errors.unit_id && <p className="mt-1 text-xs text-red-600">{errors.unit_id}</p>}
                </div>
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">Descrição *</label>
                <input value={data.description} onChange={(e) => setData('description', e.target.value)} className={field} placeholder="Ex.: Taxa condominial junho/2026" />
                {errors.description && <p className="mt-1 text-xs text-red-600">{errors.description}</p>}
            </div>

            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700">Tipo *</label>
                    <select value={data.type} onChange={(e) => setData('type', e.target.value)} className={field}>
                        {Object.entries(types).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Mês ref.</label>
                    <input type="month" value={data.reference_month} onChange={(e) => setData('reference_month', e.target.value)} className={field} />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Valor (R$) *</label>
                    <input type="number" step="0.01" min="0" value={data.amount} onChange={(e) => setData('amount', e.target.value)} disabled={lockFinancial} className={lockFinancial ? lockedField : field} />
                    {errors.amount && <p className="mt-1 text-xs text-red-600">{errors.amount}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Vencimento *</label>
                    <input type="date" value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} disabled={lockFinancial} className={lockFinancial ? lockedField : field} />
                    {errors.due_date && <p className="mt-1 text-xs text-red-600">{errors.due_date}</p>}
                </div>
            </div>
            {lockFinancial && (
                <p className="-mt-1 text-xs text-amber-600">
                    Valor e vencimento estão travados porque esta cobrança já tem boleto/PIX emitido. Para alterá-los, cancele e gere uma nova.
                </p>
            )}

            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700">Multa (%)</label>
                    <input type="number" step="0.01" min="0" max="100" value={data.fine_rate} onChange={(e) => setData('fine_rate', e.target.value)} className={field} />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Juros (% ao mês)</label>
                    <input type="number" step="0.01" min="0" max="100" value={data.interest_rate} onChange={(e) => setData('interest_rate', e.target.value)} className={field} />
                </div>
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">Observações</label>
                <textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} rows={3} className={field} />
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
