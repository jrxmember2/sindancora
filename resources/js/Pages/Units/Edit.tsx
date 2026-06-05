import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import UnitForm, { UnitFormData, PersonItem, PetItem } from './Form';
import { maskCpf, maskPhone, isoToBrDate } from '@/lib/masks';

interface ServerPerson { id: string; name: string; cpf: string | null; birth_date: string | null; phones: string[]; emails: string[] }
interface ServerPet { id: string; name: string; species: string; breed: string | null; notes: string | null }
interface Unit {
    id: string; number: string; block_id: string | null; floor: number | null;
    type: string; area_m2: number | null; fraction: number | null; status: string;
    owners: ServerPerson[]; tenants: ServerPerson[]; family: ServerPerson[]; pets: ServerPet[];
}
interface Props {
    condominium: { id: string; name: string };
    unit: Unit;
    blocks: { id: string; name: string }[];
    typeLabels: Record<string, string>;
    statusLabels: Record<string, string>;
    petSpecies: Record<string, string>;
}

const toPerson = (p: ServerPerson): PersonItem => ({
    id: p.id,
    name: p.name ?? '',
    cpf: p.cpf ? maskCpf(p.cpf) : '',
    birth_date: isoToBrDate(p.birth_date),
    phones: p.phones?.length ? p.phones.map((x) => maskPhone(x)) : [''],
    emails: p.emails?.length ? p.emails : [''],
});
const toPet = (p: ServerPet): PetItem => ({ id: p.id, name: p.name ?? '', species: p.species ?? 'dog', breed: p.breed ?? '', notes: p.notes ?? '' });

export default function UnitEdit({ condominium, unit, blocks, typeLabels, statusLabels, petSpecies }: Props) {
    const { data, setData, patch, processing, errors } = useForm<UnitFormData>({
        number: unit.number,
        block_id: unit.block_id ?? '',
        floor: unit.floor?.toString() ?? '',
        type: unit.type,
        area_m2: unit.area_m2?.toString() ?? '',
        fraction: unit.fraction?.toString() ?? '',
        status: unit.status,
        owners: (unit.owners ?? []).map(toPerson),
        tenants: (unit.tenants ?? []).map(toPerson),
        family: (unit.family ?? []).map(toPerson),
        pets: (unit.pets ?? []).map(toPet),
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
                    condominium={condominium} blocks={blocks} typeLabels={typeLabels} statusLabels={statusLabels} petSpecies={petSpecies}
                    submitLabel="Salvar Alterações"
                />
            </div>
        </AppLayout>
    );
}
