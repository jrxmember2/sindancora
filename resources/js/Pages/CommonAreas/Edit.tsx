import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import CommonAreaForm, { CommonAreaFormData } from './CommonAreaForm';
import { Attachment } from '@/Components/AttachmentList';

interface Option { value: string; label: string }
interface Area {
    id: string; condominium_id: string; name: string; description: string | null; capacity: number | null;
    requires_approval: boolean; min_advance_days: number; opening_time: string | null; closing_time: string | null;
    fee: string | null; deposit: string | null; rules: string | null; active: boolean;
}

const hhmm = (t: string | null) => (t ? t.slice(0, 5) : '');

export default function CommonAreaEdit({ area, photos, condominiums }: { area: Area; photos: Attachment[]; condominiums: Option[] }) {
    const form = useForm<CommonAreaFormData & { photos: File[] }>({
        condominium_id: area.condominium_id,
        name: area.name,
        description: area.description ?? '',
        capacity: area.capacity?.toString() ?? '',
        requires_approval: area.requires_approval,
        min_advance_days: area.min_advance_days?.toString() ?? '0',
        opening_time: hhmm(area.opening_time),
        closing_time: hhmm(area.closing_time),
        fee: area.fee ?? '',
        deposit: area.deposit ?? '',
        rules: area.rules ?? '',
        active: area.active,
        photos: [],
    });

    return (
        <AppLayout>
            <Head title="Editar Área Comum" />
            <div className="space-y-4">
                <div>
                    <Link href={route('areas.index')} className="text-sm text-gray-500 hover:text-gray-700">← Áreas Comuns</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Editar Área Comum</h1>
                </div>
                <CommonAreaForm
                    data={form.data} setData={(k, v) => form.setData(k, v)} errors={form.errors} processing={form.processing}
                    onSubmit={() => form.put(route('areas.update', area.id))}
                    condominiums={condominiums} submitLabel="Salvar" backHref={route('areas.index')}
                    photos={form.data.photos} onPhotosChange={(f) => form.setData('photos', f)}
                    existingPhotos={photos}
                />
            </div>
        </AppLayout>
    );
}
