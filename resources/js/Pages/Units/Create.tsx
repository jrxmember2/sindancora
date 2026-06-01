import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import UnitForm from './Form';

interface Props {
    condominium: { id: string; name: string };
    blocks: { id: string; name: string }[];
    typeLabels: Record<string, string>;
    statusLabels: Record<string, string>;
}

export default function UnitCreate({ condominium, blocks, typeLabels, statusLabels }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        number: '', block_id: '', floor: '', type: 'apartment', area_m2: '', fraction: '', status: 'vacant',
    });

    return (
        <AppLayout>
            <Head title="Nova Unidade" />
            <div className="space-y-4">
                <div>
                    <Link href={route('condominiums.units.index', condominium.id)} className="text-sm text-gray-500 hover:text-gray-700">← {condominium.name} · Unidades</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Nova Unidade</h1>
                </div>
                <UnitForm
                    data={data} setData={setData} errors={errors} processing={processing}
                    onSubmit={() => post(route('condominiums.units.store', condominium.id))}
                    condominium={condominium} blocks={blocks} typeLabels={typeLabels} statusLabels={statusLabels}
                    submitLabel="Criar Unidade"
                />
            </div>
        </AppLayout>
    );
}
