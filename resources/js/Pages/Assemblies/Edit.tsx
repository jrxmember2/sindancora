import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AssemblyForm, { type Option } from './AssemblyForm';

interface Assembly {
    id: string; condominium_id: string; title: string; description: string | null; scheduled_at: string | null;
}

export default function AssemblyEdit({ assembly, condominiums }: { assembly: Assembly; condominiums: Option[] }) {
    const { data, setData, patch, processing, errors } = useForm({
        condominium_id: assembly.condominium_id,
        title: assembly.title,
        description: assembly.description ?? '',
        scheduled_at: assembly.scheduled_at ? assembly.scheduled_at.slice(0, 16) : '',
    });

    const submit = (e: React.FormEvent) => { e.preventDefault(); patch(route('assemblies.update', assembly.id)); };

    return (
        <AppLayout>
            <Head title="Editar assembleia" />
            <div className="mb-4">
                <Link href={route('assemblies.show', assembly.id)} className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                    <ArrowLeft className="h-4 w-4" /> Voltar
                </Link>
                <h1 className="mt-1 text-2xl font-bold text-gray-900">Editar assembleia</h1>
            </div>
            <AssemblyForm
                data={data} setData={setData} errors={errors} processing={processing}
                condominiums={condominiums} onSubmit={submit} submitLabel="Salvar" cancelHref={route('assemblies.show', assembly.id)}
            />
        </AppLayout>
    );
}
