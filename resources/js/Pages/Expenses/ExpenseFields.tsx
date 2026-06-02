export interface Option { value: string; label: string }

export interface ExpenseFormData {
    condominium_id: string;
    category: string;
    description: string;
    amount: string;
    expense_date: string;
    supplier: string;
    notes: string;
    receipt?: File | null;
}

interface Props {
    data: ExpenseFormData;
    setData: (key: keyof ExpenseFormData, value: string | File | null) => void;
    errors: Partial<Record<string, string>>;
    condominiums: Option[];
    categories: Record<string, string>;
    showReceipt?: boolean;
}

const field = 'mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';

export default function ExpenseFields({ data, setData, errors, condominiums, categories, showReceipt }: Props) {
    return (
        <div className="space-y-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label className="block text-sm font-medium text-gray-700">Condomínio *</label>
                    <select value={data.condominium_id} onChange={(e) => setData('condominium_id', e.target.value)} className={field}>
                        <option value="">Selecione…</option>
                        {condominiums.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                    </select>
                    {errors.condominium_id && <p className="mt-1 text-xs text-red-600">{errors.condominium_id}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Categoria *</label>
                    <select value={data.category} onChange={(e) => setData('category', e.target.value)} className={field}>
                        {Object.entries(categories).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                    </select>
                </div>
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">Descrição *</label>
                <input value={data.description} onChange={(e) => setData('description', e.target.value)} className={field} placeholder="Ex.: Conta de energia — junho" />
                {errors.description && <p className="mt-1 text-xs text-red-600">{errors.description}</p>}
            </div>

            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div>
                    <label className="block text-sm font-medium text-gray-700">Valor (R$) *</label>
                    <input type="number" step="0.01" min="0" value={data.amount} onChange={(e) => setData('amount', e.target.value)} className={field} />
                    {errors.amount && <p className="mt-1 text-xs text-red-600">{errors.amount}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Data *</label>
                    <input type="date" value={data.expense_date} onChange={(e) => setData('expense_date', e.target.value)} className={field} />
                    {errors.expense_date && <p className="mt-1 text-xs text-red-600">{errors.expense_date}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Fornecedor</label>
                    <input value={data.supplier} onChange={(e) => setData('supplier', e.target.value)} className={field} />
                </div>
            </div>

            {showReceipt && (
                <div>
                    <label className="block text-sm font-medium text-gray-700">Comprovante (opcional)</label>
                    <input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" onChange={(e) => setData('receipt', e.target.files?.[0] ?? null)} className="mt-1 w-full text-sm text-gray-600" />
                    {errors.receipt && <p className="mt-1 text-xs text-red-600">{errors.receipt}</p>}
                </div>
            )}

            <div>
                <label className="block text-sm font-medium text-gray-700">Observações</label>
                <textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} rows={3} className={field} />
            </div>
        </div>
    );
}
