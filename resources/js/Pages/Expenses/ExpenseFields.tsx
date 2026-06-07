export interface Option { value: string; label: string }

export interface ExpenseFormData {
    condominium_id: string;
    category: string;
    status: string;
    description: string;
    amount: string;
    expense_date: string;
    due_date: string;
    paid_at: string;
    paid_amount: string;
    payment_method: string;
    supplier_id: string;
    supplier: string;
    document_number: string;
    reminder_days: string;
    notes: string;
    receipt?: File | null;
}

interface Props {
    data: ExpenseFormData;
    setData: (key: keyof ExpenseFormData, value: string | File | null) => void;
    errors: Partial<Record<keyof ExpenseFormData | 'receipt', string>>;
    condominiums: Option[];
    suppliers: Option[];
    categories: Record<string, string>;
    statuses: Record<string, string>;
    paymentMethods: Record<string, string>;
    showReceipt?: boolean;
}

const field = 'mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';

export default function ExpenseFields({
    data,
    setData,
    errors,
    condominiums,
    suppliers,
    categories,
    statuses,
    paymentMethods,
    showReceipt,
}: Props) {
    const isPaid = data.status === 'paid';

    return (
        <div className="space-y-5">
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div>
                    <label className="block text-sm font-medium text-gray-700">Condomínio *</label>
                    <select value={data.condominium_id} onChange={(e) => setData('condominium_id', e.target.value)} className={field}>
                        <option value="">Selecione...</option>
                        {condominiums.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                    </select>
                    {errors.condominium_id && <p className="mt-1 text-xs text-red-600">{errors.condominium_id}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Categoria *</label>
                    <select value={data.category} onChange={(e) => setData('category', e.target.value)} className={field}>
                        {Object.entries(categories).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                    {errors.category && <p className="mt-1 text-xs text-red-600">{errors.category}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Status</label>
                    <select value={data.status} onChange={(e) => setData('status', e.target.value)} className={field}>
                        {Object.entries(statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                    {errors.status && <p className="mt-1 text-xs text-red-600">{errors.status}</p>}
                </div>
            </div>

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_220px]">
                <div>
                    <label className="block text-sm font-medium text-gray-700">Descrição *</label>
                    <input value={data.description} onChange={(e) => setData('description', e.target.value)} className={field} placeholder="Ex.: Limpeza mensal, contrato de elevador, conta de energia" />
                    {errors.description && <p className="mt-1 text-xs text-red-600">{errors.description}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Nº documento</label>
                    <input value={data.document_number} onChange={(e) => setData('document_number', e.target.value)} className={field} placeholder="NF, boleto..." />
                    {errors.document_number && <p className="mt-1 text-xs text-red-600">{errors.document_number}</p>}
                </div>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700">Valor (R$) *</label>
                    <input type="number" step="0.01" min="0" value={data.amount} onChange={(e) => setData('amount', e.target.value)} className={field} />
                    {errors.amount && <p className="mt-1 text-xs text-red-600">{errors.amount}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Vencimento *</label>
                    <input type="date" value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} className={field} />
                    {errors.due_date && <p className="mt-1 text-xs text-red-600">{errors.due_date}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Competência</label>
                    <input type="date" value={data.expense_date} onChange={(e) => setData('expense_date', e.target.value)} className={field} />
                    {errors.expense_date && <p className="mt-1 text-xs text-red-600">{errors.expense_date}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Lembrar com</label>
                    <div className="mt-1 flex rounded-lg border border-gray-200 focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500">
                        <input type="number" min="0" max="60" value={data.reminder_days} onChange={(e) => setData('reminder_days', e.target.value)} className="w-full rounded-l-lg border-0 px-3 py-2 text-sm focus:outline-none focus:ring-0" />
                        <span className="flex items-center rounded-r-lg bg-gray-50 px-3 text-xs text-gray-500">dias</span>
                    </div>
                    {errors.reminder_days && <p className="mt-1 text-xs text-red-600">{errors.reminder_days}</p>}
                </div>
            </div>

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div>
                    <label className="block text-sm font-medium text-gray-700">Fornecedor cadastrado</label>
                    <select value={data.supplier_id} onChange={(e) => setData('supplier_id', e.target.value)} className={field}>
                        <option value="">Nenhum</option>
                        {suppliers.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                    </select>
                    {errors.supplier_id && <p className="mt-1 text-xs text-red-600">{errors.supplier_id}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Fornecedor livre</label>
                    <input value={data.supplier} onChange={(e) => setData('supplier', e.target.value)} className={field} placeholder="Use quando não houver cadastro" />
                    {errors.supplier && <p className="mt-1 text-xs text-red-600">{errors.supplier}</p>}
                </div>
            </div>

            {isPaid && (
                <div className="grid grid-cols-1 gap-4 rounded-lg border border-emerald-100 bg-emerald-50/60 p-4 sm:grid-cols-3">
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Data de pagamento</label>
                        <input type="date" value={data.paid_at} onChange={(e) => setData('paid_at', e.target.value)} className={field} />
                        {errors.paid_at && <p className="mt-1 text-xs text-red-600">{errors.paid_at}</p>}
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Valor pago</label>
                        <input type="number" step="0.01" min="0" value={data.paid_amount} onChange={(e) => setData('paid_amount', e.target.value)} className={field} />
                        {errors.paid_amount && <p className="mt-1 text-xs text-red-600">{errors.paid_amount}</p>}
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Forma de pagamento</label>
                        <select value={data.payment_method} onChange={(e) => setData('payment_method', e.target.value)} className={field}>
                            <option value="">Não informado</option>
                            {Object.entries(paymentMethods).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                        {errors.payment_method && <p className="mt-1 text-xs text-red-600">{errors.payment_method}</p>}
                    </div>
                </div>
            )}

            {showReceipt && (
                <div>
                    <label className="block text-sm font-medium text-gray-700">Nota fiscal ou comprovante</label>
                    <input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" onChange={(e) => setData('receipt', e.target.files?.[0] ?? null)} className="mt-1 w-full text-sm text-gray-600" />
                    {errors.receipt && <p className="mt-1 text-xs text-red-600">{errors.receipt}</p>}
                </div>
            )}

            <div>
                <label className="block text-sm font-medium text-gray-700">Observações</label>
                <textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} rows={3} className={field} />
                {errors.notes && <p className="mt-1 text-xs text-red-600">{errors.notes}</p>}
            </div>
        </div>
    );
}
