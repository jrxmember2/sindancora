import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import ChargeForm, { type Option, type UnitOption } from './ChargeForm';

interface Charge {
    id: string; condominium_id: string; unit_id: string; type: string; description: string;
    reference_month: string | null; amount: string; due_date: string;
    fine_rate: string; interest_rate: string; notes: string | null;
}
interface Props { charge: Charge; condominiums: Option[]; units: UnitOption[]; types: Record<string, string>; lockFinancial: boolean }

export default function ChargeEdit({ charge, condominiums, units, types, lockFinancial }: Props) {
    const { data, setData, patch, processing, errors } = useForm({
        condominium_id: charge.condominium_id,
        unit_id: charge.unit_id,
        type: charge.type,
        description: charge.description,
        reference_month: charge.reference_month ?? '',
        amount: String(charge.amount),
        due_date: charge.due_date?.slice(0, 10) ?? '',
        fine_rate: String(charge.fine_rate),
        interest_rate: String(charge.interest_rate),
        notes: charge.notes ?? '',
    });

    const submit = (e: React.FormEvent) => { e.preventDefault(); patch(route('charges.update', charge.id)); };

    return (
        <AppLayout>
            <Head title="Editar cobrança" />
            <div className="mb-4">
                <Link href={route('charges.show', charge.id)} className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                    <ArrowLeft className="h-4 w-4" /> Voltar
                </Link>
                <h1 className="mt-1 text-2xl font-bold text-gray-900">Editar cobrança</h1>
            </div>
            <ChargeForm
                data={data} setData={setData} errors={errors} processing={processing}
                condominiums={condominiums} units={units} types={types}
                onSubmit={submit} submitLabel="Salvar" cancelHref={route('charges.show', charge.id)}
                lockFinancial={lockFinancial}
            />
        </AppLayout>
    );
}
