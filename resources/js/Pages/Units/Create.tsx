import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import UnitForm, { UnitFormData, emptyPerson } from './Form';

interface Props {
    condominium: { id: string; name: string };
    blocks: { id: string; name: string }[];
    typeLabels: Record<string, string>;
    statusLabels: Record<string, string>;
    petSpecies: Record<string, string>;
    vehicleTypes: Record<string, string>;
}

export default function UnitCreate({ condominium, blocks, typeLabels, statusLabels, petSpecies, vehicleTypes }: Props) {
    const { data, setData, post, processing, errors } = useForm<UnitFormData>({
        number: '', block_id: '', floor: '', type: 'apartment', area_m2: '', fraction: '', status: 'vacant',
        owners: [emptyPerson()], tenants: [], family: [], pets: [], vehicles: [],
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
                    condominium={condominium} blocks={blocks} typeLabels={typeLabels} statusLabels={statusLabels} petSpecies={petSpecies} vehicleTypes={vehicleTypes}
                    submitLabel="Criar Unidade"
                />
            </div>
        </AppLayout>
    );
}
