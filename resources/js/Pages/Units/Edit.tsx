import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import UnitForm from './Form';

interface Unit {
    id: string; number: string; block_id: string | null; floor: number | null;
    type: string; area_m2: number | null; fraction: number | null; status: string;
}
interface Props {
    condominium: { id: string; name: string };
    unit: Unit;
    blocks: { id: string; name: string }[];
    typeLabels: Record<string, string>;
    statusLabels: Record<string, string>;
}

export default function UnitEdit({ condominium, unit, blocks, typeLabels, statusLabels }: Props) {
    const { data, setData, patch, processing, errors } = useForm({
        number: unit.number,
        block_id: unit.block_id ?? '',
        floor: unit.floor?.toString() ?? '',
        type: unit.type,
        area_m2: unit.area_m2?.toString() ?? '',
        fraction: unit.fraction?.toString() ?? '',
        status: unit.status,
    });

    return (
        <AppLayout>
            <Head title={`Editar Unidade ${unit.number}`} />
            <div className="space-y-4">
                <div>
                    <Link href={route('condominiums.units.index', condominium.id)} className="text-sm text-gray-500 hover:text-gray-700">← {condominium.name} · Unidades</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Editar Unidade {unit.number}</h1>
                </div>
                <UnitForm
                    data={data} setData={setData} errors={errors} processing={processing}
                    onSubmit={() => patch(route('condominiums.units.update', [condominium.id, unit.id]))}
                    condominium={condominium} blocks={blocks} typeLabels={typeLabels} statusLabels={statusLabels}
                    submitLabel="Salvar Alterações"
                />
            </div>
        </AppLayout>
    );
}
