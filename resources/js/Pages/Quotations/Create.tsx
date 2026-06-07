import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import QuotationForm, { type Option, type QuotationFormData } from './QuotationForm';

interface Props {
    condominiums: Option[];
    categories: Record<string, string>;
    statuses: Record<string, string>;
}

export default function QuotationCreate({ condominiums, categories, statuses }: Props) {
    const { data, setData, post, processing, errors } = useForm<QuotationFormData>({
        condominium_id: '',
        category: '',
        title: '',
        description: '',
        status: 'collecting',
        response_deadline: '',
        notes: '',
    });

    return (
        <AppLayout>
            <Head title="Novo orçamento" />
            <div className="space-y-4">
                <div>
                    <Link href={route('quotations.index')} className="text-sm text-gray-500 hover:text-gray-700">← Orçamentos</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Novo orçamento</h1>
                </div>
                <QuotationForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    processing={processing}
                    onSubmit={() => post(route('quotations.store'))}
                    submitLabel="Criar orçamento"
                    backHref={route('quotations.index')}
                    condominiums={condominiums}
                    categories={categories}
                    statuses={statuses}
                />
            </div>
        </AppLayout>
    );
}
