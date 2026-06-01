import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import PersonForm from './PersonForm';

export default function PersonCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '', cpf: '', email: '', phone: '', phone2: '', birth_date: '',
        zip_code: '', street: '', number: '', complement: '', neighborhood: '', city: '', state: '', notes: '',
    });

    return (
        <AppLayout>
            <Head title="Nova Pessoa" />
            <div className="space-y-4">
                <div>
                    <Link href={route('persons.index')} className="text-sm text-gray-500 hover:text-gray-700">← Pessoas</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Nova Pessoa</h1>
                </div>
                <PersonForm
                    data={data} setData={setData} errors={errors} processing={processing}
                    onSubmit={() => post(route('persons.store'))}
                    submitLabel="Cadastrar" backHref={route('persons.index')}
                />
            </div>
        </AppLayout>
    );
}
