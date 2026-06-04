import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import CommonAreaForm, { CommonAreaFormData } from './CommonAreaForm';

interface Option { value: string; label: string }

export default function CommonAreaCreate({ condominiums }: { condominiums: Option[] }) {
    const form = useForm<CommonAreaFormData & { photos: File[] }>({
        condominium_id: condominiums.length === 1 ? condominiums[0].value : '',
        name: '', description: '', capacity: '', requires_approval: true, min_advance_days: '0',
        opening_time: '', closing_time: '', fee: '', deposit: '', rules: '', active: true,
        photos: [],
    });

    return (
        <AppLayout>
            <Head title="Nova Área Comum" />
            <div className="space-y-4">
                <div>
                    <Link href={route('areas.index')} className="text-sm text-gray-500 hover:text-gray-700">← Áreas Comuns</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Nova Área Comum</h1>
                </div>
                <CommonAreaForm
                    data={form.data} setData={(k, v) => form.setData(k, v)} errors={form.errors} processing={form.processing}
                    onSubmit={() => form.post(route('areas.store'))}
                    condominiums={condominiums} submitLabel="Criar" backHref={route('areas.index')}
                    photos={form.data.photos} onPhotosChange={(f) => form.setData('photos', f)}
                />
            </div>
        </AppLayout>
    );
}
