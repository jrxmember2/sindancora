import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import ExpenseFields, { type Option } from './ExpenseFields';

interface Expense {
    id: string; condominium_id: string; category: string; description: string;
    amount: string; expense_date: string; supplier: string | null; notes: string | null;
}
interface Props { expense: Expense; condominiums: Option[]; categories: Record<string, string> }

export default function ExpenseEdit({ expense, condominiums, categories }: Props) {
    const { data, setData, patch, processing, errors } = useForm({
        condominium_id: expense.condominium_id,
        category: expense.category,
        description: expense.description,
        amount: String(expense.amount),
        expense_date: expense.expense_date?.slice(0, 10) ?? '',
        supplier: expense.supplier ?? '',
        notes: expense.notes ?? '',
    });

    const submit = (e: React.FormEvent) => { e.preventDefault(); patch(route('expenses.update', expense.id)); };

    return (
        <AppLayout>
            <Head title="Editar despesa" />
            <div className="mb-4">
                <Link href={route('expenses.index')} className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                    <ArrowLeft className="h-4 w-4" /> Despesas
                </Link>
                <h1 className="mt-1 text-2xl font-bold text-gray-900">Editar despesa</h1>
            </div>
            <form onSubmit={submit} className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <ExpenseFields data={data} setData={setData} errors={errors} condominiums={condominiums} categories={categories} />
                <div className="mt-4 flex justify-end gap-2">
                    <Link href={route('expenses.index')} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</Link>
                    <button type="submit" disabled={processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        {processing ? 'Salvando…' : 'Salvar'}
                    </button>
                </div>
            </form>
        </AppLayout>
    );
}
