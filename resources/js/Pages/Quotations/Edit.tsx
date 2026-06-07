import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import QuotationForm, { type Option, type QuotationFormData } from './QuotationForm';

interface Quotation {
    id: string;
    condominium_id: string;
    category: string | null;
    title: string;
    description: string | null;
    status: string;
    response_deadline: string | null;
    notes: string | null;
}

interface Props {
    quotation: Quotation;
    condominiums: Option[];
    categories: Record<string, string>;
    statuses: Record<string, string>;
}

const dateOnly = (value?: string | null) => value?.slice(0, 10) ?? '';

export default function QuotationEdit({ quotation, condominiums, categories, statuses }: Props) {
    const { data, setData, patch, processing, errors } = useForm<QuotationFormData>({
        condominium_id: quotation.condominium_id,
        category: quotation.category ?? '',
        title: quotation.title,
        description: quotation.description ?? '',
        status: quotation.status,
        response_deadline: dateOnly(quotation.response_deadline),
        notes: quotation.notes ?? '',
    });

    return (
        <AppLayout>
            <Head title="Editar orçamento" />
            <div className="space-y-4">
                <div>
                    <Link href={route('quotations.show', quotation.id)} className="text-sm text-gray-500 hover:text-gray-700">← Orçamento</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Editar orçamento</h1>
                </div>
                <QuotationForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    processing={processing}
                    onSubmit={() => patch(route('quotations.update', quotation.id))}
                    submitLabel="Salvar alterações"
                    backHref={route('quotations.show', quotation.id)}
                    condominiums={condominiums}
                    categories={categories}
                    statuses={statuses}
                />
            </div>
        </AppLayout>
    );
}
