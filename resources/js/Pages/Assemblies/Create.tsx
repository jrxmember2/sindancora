import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AssemblyForm, { type Option } from './AssemblyForm';

export default function AssemblyCreate({ condominiums }: { condominiums: Option[] }) {
    const { data, setData, post, processing, errors } = useForm({
        condominium_id: '', title: '', description: '', scheduled_at: '',
    });

    const submit = (e: React.FormEvent) => { e.preventDefault(); post(route('assemblies.store')); };

    return (
        <AppLayout>
            <Head title="Nova assembleia" />
            <div className="mb-4">
                <Link href={route('assemblies.index')} className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                    <ArrowLeft className="h-4 w-4" /> Assembleias
                </Link>
                <h1 className="mt-1 text-2xl font-bold text-gray-900">Nova assembleia</h1>
            </div>
            <AssemblyForm
                data={data} setData={setData} errors={errors} processing={processing}
                condominiums={condominiums} onSubmit={submit} submitLabel="Criar" cancelHref={route('assemblies.index')}
            />
        </AppLayout>
    );
}
