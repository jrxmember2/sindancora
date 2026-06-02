import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import ChargeForm, { type Option, type UnitOption } from './ChargeForm';

interface Props { condominiums: Option[]; units: UnitOption[]; types: Record<string, string> }

export default function ChargeCreate({ condominiums, units, types }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        condominium_id: '', unit_id: '', type: 'condo_fee', description: '',
        reference_month: '', amount: '', due_date: '', fine_rate: '2', interest_rate: '1', notes: '',
    });

    const submit = (e: React.FormEvent) => { e.preventDefault(); post(route('charges.store')); };

    return (
        <AppLayout>
            <Head title="Nova cobrança" />
            <div className="mb-4">
                <Link href={route('charges.index')} className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                    <ArrowLeft className="h-4 w-4" /> Cobranças
                </Link>
                <h1 className="mt-1 text-2xl font-bold text-gray-900">Nova cobrança</h1>
            </div>
            <ChargeForm
                data={data} setData={setData} errors={errors} processing={processing}
                condominiums={condominiums} units={units} types={types}
                onSubmit={submit} submitLabel="Criar cobrança" cancelHref={route('charges.index')}
            />
        </AppLayout>
    );
}
