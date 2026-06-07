import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CalendarCheck, Pencil, Receipt, Trash2, Wrench } from 'lucide-react';
import type { PageProps } from '@/types';

interface Option { value: string; label: string }

interface ExecutionExpense {
    id: string;
    status: string;
    due_date: string | null;
    amount: string;
    description: string;
}

interface ExecutionRecord {
    id: string;
    done_date: string;
    cost: string | null;
    notes: string | null;
    supplier: { id: string; name: string } | null;
    author: { id: string; name: string } | null;
    expense: ExecutionExpense | null;
}

interface Plan {
    id: string;
    title: string;
    description: string | null;
    category: string | null;
    frequency: string;
    next_due_date: string | null;
    last_done_date: string | null;
    alert_days: number;
    is_active: boolean;
    status: string | null;
    days_until_due: number | null;
    condominium: { id: string; name: string } | null;
    supplier: { id: string; name: string } | null;
    records: ExecutionRecord[];
}

interface Props {
    plan: Plan;
    categories: Record<string, string>;
    frequencies: Record<string, string>;
    suppliers: Option[];
    canGenerateExpense: boolean;
}

const expenseStatus: Record<string, string> = {
    pending: 'Pendente',
    paid: 'Paga',
    overdue: 'Vencida',
    cancelled: 'Cancelada',
};

function fmtDate(iso: string | null) {
    return iso ? new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '-';
}

function fmtMoney(value: string | null) {
    return value !== null && value !== ''
        ? Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
        : '-';
}

export default function MaintenanceShow({ plan, categories, frequencies, suppliers, canGenerateExpense }: Props) {
    const { auth, tenant } = usePage<PageProps>().props;
    const permissions = auth.user?.permissions ?? [];
    const can = (permission: string) => permissions.includes('*') || permissions.includes(permission);
    const planAllowsFinancial = auth.user?.is_super_admin || (tenant?.plan?.modules ?? []).includes('financial');
    const canOpenExpenses = can('expenses:read') && planAllowsFinancial;
    const canEditExpenses = can('expenses:update') && planAllowsFinancial;

    const today = new Date().toISOString().slice(0, 10);
    const form = useForm({
        done_date: today,
        supplier_id: plan.supplier?.id ?? '',
        cost: '',
        notes: '',
        generate_expense: false,
        expense_due_date: today,
        expense_document_number: '',
        expense_reminder_days: '3',
    });

    const submit = () => form.post(route('maintenance.executions.store', plan.id), {
        preserveScroll: true,
        onSuccess: () => form.reset('cost', 'notes', 'generate_expense', 'expense_document_number'),
    });

    const removeRecord = (id: string) => {
        if (confirm('Remover esta execução?')) {
            router.delete(route('maintenance.executions.destroy', id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout>
            <Head title={plan.title} />
            <div className="mx-auto max-w-3xl space-y-6">
                <div className="flex items-start justify-between">
                    <div>
                        <Link href={route('maintenance.index')} className="text-sm text-gray-500 hover:text-gray-700">← Manutenção</Link>
                        <div className="mt-1 flex items-center gap-2">
                            <Wrench className="h-6 w-6 text-blue-600" />
                            <h1 className="text-2xl font-bold text-gray-900">{plan.title}</h1>
                            {!plan.is_active && <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">inativa</span>}
                        </div>
                    </div>
                    {can('maintenance:update') && (
                        <Link href={route('maintenance.edit', plan.id)} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                            <Pencil className="h-4 w-4" /> Editar
                        </Link>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-4">
                    <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <p className="text-xs uppercase tracking-wide text-gray-500">Condomínio</p>
                        <p className="mt-1 text-sm font-medium text-gray-900">{plan.condominium?.name ?? '-'}</p>
                    </div>
                    <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <p className="text-xs uppercase tracking-wide text-gray-500">Categoria</p>
                        <p className="mt-1 text-sm font-medium text-gray-900">{plan.category ? (categories[plan.category] ?? plan.category) : '-'}</p>
                    </div>
                    <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <p className="text-xs uppercase tracking-wide text-gray-500">Recorrência</p>
                        <p className="mt-1 text-sm font-medium text-gray-900">{frequencies[plan.frequency] ?? plan.frequency}</p>
                    </div>
                    <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <p className="text-xs uppercase tracking-wide text-gray-500">Próxima prevista</p>
                        <p className={`mt-1 text-sm font-medium ${plan.status === 'overdue' ? 'text-red-600' : plan.status === 'due_soon' ? 'text-amber-600' : 'text-gray-900'}`}>{fmtDate(plan.next_due_date)}</p>
                    </div>
                </div>

                <div className="space-y-2 rounded-xl border border-gray-100 bg-white p-6 text-sm text-gray-700 shadow-sm">
                    <p>Fornecedor padrão: <span className="font-medium">{plan.supplier?.name ?? '-'}</span></p>
                    <p>Última execução: <span className="font-medium">{fmtDate(plan.last_done_date)}</span></p>
                    <p>Alerta: {plan.alert_days} dia(s) de antecedência</p>
                    {plan.description && <p className="border-t border-gray-100 pt-2 text-gray-600">{plan.description}</p>}
                </div>

                <div className="space-y-4 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 className="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                        <CalendarCheck className="h-4 w-4" /> Execuções
                    </h2>

                    {can('maintenance:update') && (
                        <div className="grid gap-3 rounded-lg bg-gray-50 p-4 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-600">Data realizada</label>
                                <input type="date" value={form.data.done_date} onChange={e => form.setData('done_date', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                                {form.errors.done_date && <p className="mt-1 text-xs text-red-600">{form.errors.done_date}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-600">Fornecedor</label>
                                <select value={form.data.supplier_id} onChange={e => form.setData('supplier_id', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                                    <option value="">-</option>
                                    {suppliers.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-600">Custo (R$)</label>
                                <input type="number" min={0} step="0.01" value={form.data.cost} onChange={e => form.setData('cost', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                                {form.errors.cost && <p className="mt-1 text-xs text-red-600">{form.errors.cost}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-600">Observações</label>
                                <input value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                            </div>

                            {canGenerateExpense && (
                                <div className="space-y-3 rounded-lg border border-blue-100 bg-blue-50/50 p-3 sm:col-span-2">
                                    <label className="flex items-center gap-2 text-sm font-medium text-gray-700">
                                        <input type="checkbox" checked={form.data.generate_expense} onChange={e => form.setData('generate_expense', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                        Gerar conta a pagar
                                    </label>

                                    {form.data.generate_expense && (
                                        <div className="grid gap-3 sm:grid-cols-3">
                                            <div>
                                                <label className="mb-1 block text-xs font-medium text-gray-600">Vencimento</label>
                                                <input type="date" value={form.data.expense_due_date} onChange={e => form.setData('expense_due_date', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                                                {form.errors.expense_due_date && <p className="mt-1 text-xs text-red-600">{form.errors.expense_due_date}</p>}
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-medium text-gray-600">Nº documento</label>
                                                <input value={form.data.expense_document_number} onChange={e => form.setData('expense_document_number', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                                                {form.errors.expense_document_number && <p className="mt-1 text-xs text-red-600">{form.errors.expense_document_number}</p>}
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-medium text-gray-600">Lembrar com</label>
                                                <div className="flex rounded-lg border border-gray-200 bg-white">
                                                    <input type="number" min={0} max={60} value={form.data.expense_reminder_days} onChange={e => form.setData('expense_reminder_days', e.target.value)} className="w-full rounded-l-lg border-0 px-3 py-2 text-sm focus:outline-none focus:ring-0" />
                                                    <span className="flex items-center rounded-r-lg bg-gray-50 px-3 text-xs text-gray-500">dias</span>
                                                </div>
                                                {form.errors.expense_reminder_days && <p className="mt-1 text-xs text-red-600">{form.errors.expense_reminder_days}</p>}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}

                            <div className="sm:col-span-2">
                                <button onClick={submit} disabled={form.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50">
                                    {form.processing ? 'Salvando...' : 'Registrar execução'}
                                </button>
                                {plan.frequency !== 'once' && <span className="ml-3 text-xs text-gray-500">A próxima data será recalculada automaticamente.</span>}
                            </div>
                        </div>
                    )}

                    <div className="overflow-hidden rounded-lg border border-gray-100">
                        <table className="w-full text-sm">
                            <thead className="border-b border-gray-100 bg-gray-50">
                                <tr>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Data</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Fornecedor</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Custo</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Conta</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Obs.</th>
                                    <th className="px-3 py-2" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {plan.records.length === 0 && (
                                    <tr><td colSpan={6} className="px-3 py-6 text-center text-sm text-gray-500">Nenhuma execução registrada.</td></tr>
                                )}
                                {plan.records.map(record => (
                                    <tr key={record.id}>
                                        <td className="px-3 py-2 text-gray-700">{fmtDate(record.done_date)}</td>
                                        <td className="px-3 py-2 text-xs text-gray-600">{record.supplier?.name ?? '-'}</td>
                                        <td className="px-3 py-2 text-xs text-gray-600">{fmtMoney(record.cost)}</td>
                                        <td className="px-3 py-2 text-xs text-gray-600">
                                            {record.expense ? (
                                                canOpenExpenses ? (
                                                    <Link href={canEditExpenses ? route('expenses.edit', record.expense.id) : route('expenses.index')} className="inline-flex items-center gap-1 text-blue-600 hover:underline">
                                                        <Receipt className="h-3.5 w-3.5" />
                                                        {fmtMoney(record.expense.amount)} · {expenseStatus[record.expense.status] ?? record.expense.status}
                                                    </Link>
                                                ) : (
                                                    <span>{fmtMoney(record.expense.amount)} · {expenseStatus[record.expense.status] ?? record.expense.status}</span>
                                                )
                                            ) : '-'}
                                        </td>
                                        <td className="px-3 py-2 text-xs text-gray-600">{record.notes ?? '-'}</td>
                                        <td className="px-3 py-2 text-right">
                                            {can('maintenance:delete') && (
                                                <button onClick={() => removeRecord(record.id)} className="rounded p-1.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-500"><Trash2 className="h-4 w-4" /></button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
