import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { PageProps } from '@/types';
import ExpenseFields, { type Option } from './ExpenseFields';

interface Expense {
    id: string;
    condominium_id: string;
    category: string;
    status: string;
    description: string;
    amount: string;
    expense_date: string | null;
    due_date: string | null;
    paid_at: string | null;
    paid_amount: string | null;
    payment_method: string | null;
    supplier_id: string | null;
    supplier: string | null;
    document_number: string | null;
    reminder_days: number | null;
    notes: string | null;
    maintenance_record: { plan: { id: string; title: string } | null } | null;
}

interface Props {
    expense: Expense;
    condominiums: Option[];
    suppliers: Option[];
    categories: Record<string, string>;
    statuses: Record<string, string>;
    paymentMethods: Record<string, string>;
}

const dateOnly = (value?: string | null) => value?.slice(0, 10) ?? '';
const today = new Date().toISOString().slice(0, 10);

export default function ExpenseEdit({ expense, condominiums, suppliers, categories, statuses, paymentMethods }: Props) {
    const { auth, tenant } = usePage<PageProps>().props;
    const permissions = auth.user?.permissions ?? [];
    const can = (permission: string) => permissions.includes('*') || permissions.includes(permission);
    const canOpenMaintenance = can('maintenance:read') && (auth.user?.is_super_admin || (tenant?.plan?.modules ?? []).includes('maintenance'));

    const { data, setData, patch, processing, errors } = useForm({
        condominium_id: expense.condominium_id,
        category: expense.category,
        status: expense.status,
        description: expense.description,
        amount: String(expense.amount),
        expense_date: dateOnly(expense.expense_date),
        due_date: dateOnly(expense.due_date),
        paid_at: dateOnly(expense.paid_at) || today,
        paid_amount: expense.paid_amount ? String(expense.paid_amount) : String(expense.amount),
        payment_method: expense.payment_method ?? '',
        supplier_id: expense.supplier_id ?? '',
        supplier: expense.supplier ?? '',
        document_number: expense.document_number ?? '',
        reminder_days: String(expense.reminder_days ?? 3),
        notes: expense.notes ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(route('expenses.update', expense.id));
    };

    return (
        <AppLayout>
            <Head title="Editar conta a pagar" />
            <div className="mb-4">
                <Link href={route('expenses.index')} className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                    <ArrowLeft className="h-4 w-4" /> Contas a pagar
                </Link>
                <h1 className="mt-1 text-2xl font-bold text-gray-900">Editar conta a pagar</h1>
            </div>
            <form onSubmit={submit} className="rounded-lg border border-gray-100 bg-white p-5 shadow-sm">
                {expense.maintenance_record?.plan && (
                    <div className="mb-5 rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                        Origem:{' '}
                        {canOpenMaintenance ? (
                            <Link href={route('maintenance.show', expense.maintenance_record.plan.id)} className="font-medium underline">
                                {expense.maintenance_record.plan.title}
                            </Link>
                        ) : (
                            <span className="font-medium">{expense.maintenance_record.plan.title}</span>
                        )}
                    </div>
                )}
                <ExpenseFields
                    data={data}
                    setData={setData}
                    errors={errors}
                    condominiums={condominiums}
                    suppliers={suppliers}
                    categories={categories}
                    statuses={statuses}
                    paymentMethods={paymentMethods}
                />
                <div className="mt-5 flex justify-end gap-2">
                    <Link href={route('expenses.index')} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</Link>
                    <button type="submit" disabled={processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        {processing ? 'Salvando...' : 'Salvar'}
                    </button>
                </div>
            </form>
        </AppLayout>
    );
}
