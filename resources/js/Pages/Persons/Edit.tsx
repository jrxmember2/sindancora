import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import PersonForm from './PersonForm';

interface Person {
    id: string; name: string; cpf: string | null; email: string | null; phone: string | null;
    phone2: string | null; birth_date: string | null; zip_code: string | null; street: string | null;
    number: string | null; complement: string | null; neighborhood: string | null; city: string | null;
    state: string | null; notes: string | null;
}
interface Props { person: Person }

export default function PersonEdit({ person }: Props) {
    const { data, setData, patch, processing, errors } = useForm({
        name: person.name, cpf: person.cpf ?? '', email: person.email ?? '', phone: person.phone ?? '',
        phone2: person.phone2 ?? '', birth_date: person.birth_date ?? '', zip_code: person.zip_code ?? '',
        street: person.street ?? '', number: person.number ?? '', complement: person.complement ?? '',
        neighborhood: person.neighborhood ?? '', city: person.city ?? '', state: person.state ?? '',
        notes: person.notes ?? '',
    });

    return (
        <AppLayout>
            <Head title={`Editar — ${person.name}`} />
            <div className="space-y-4">
                <div>
                    <Link href={route('persons.show', person.id)} className="text-sm text-gray-500 hover:text-gray-700">← {person.name}</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Editar Pessoa</h1>
                </div>
                <PersonForm
                    data={data} setData={setData} errors={errors} processing={processing}
                    onSubmit={() => patch(route('persons.update', person.id))}
                    submitLabel="Salvar Alterações" backHref={route('persons.show', person.id)}
                />
            </div>
        </AppLayout>
    );
}
