import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import ExpenseFields, { type Option } from './ExpenseFields';

interface Props {
    condominiums: Option[];
    suppliers: Option[];
    categories: Record<string, string>;
    statuses: Record<string, string>;
    paymentMethods: Record<string, string>;
}

const today = new Date().toISOString().slice(0, 10);

export default function ExpenseCreate({ condominiums, suppliers, categories, statuses, paymentMethods }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        condominium_id: '',
        category: 'other',
        status: 'pending',
        description: '',
        amount: '',
        expense_date: today,
        due_date: today,
        paid_at: today,
        paid_amount: '',
        payment_method: '',
        supplier_id: '',
        supplier: '',
        document_number: '',
        reminder_days: '3',
        notes: '',
        receipt: null as File | null,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('expenses.store'), { forceFormData: true });
    };

    return (
        <AppLayout>
            <Head title="Nova conta a pagar" />
            <div className="mb-4">
                <Link href={route('expenses.index')} className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                    <ArrowLeft className="h-4 w-4" /> Contas a pagar
                </Link>
                <h1 className="mt-1 text-2xl font-bold text-gray-900">Nova conta a pagar</h1>
            </div>
            <form onSubmit={submit} className="rounded-lg border border-gray-100 bg-white p-5 shadow-sm">
                <ExpenseFields
                    data={data}
                    setData={setData}
                    errors={errors}
                    condominiums={condominiums}
                    suppliers={suppliers}
                    categories={categories}
                    statuses={statuses}
                    paymentMethods={paymentMethods}
                    showReceipt
                />
                <div className="mt-5 flex justify-end gap-2">
                    <Link href={route('expenses.index')} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</Link>
                    <button type="submit" disabled={processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        {processing ? 'Salvando...' : 'Cadastrar conta'}
                    </button>
                </div>
            </form>
        </AppLayout>
    );
}
