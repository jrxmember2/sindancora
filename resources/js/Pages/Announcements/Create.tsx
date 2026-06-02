import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';
import AnnouncementForm, { AnnouncementFormData } from './AnnouncementForm';

interface Option { value: string; label: string }
interface Props {
    condominiums: Option[];
    categories: Record<string, string>;
    urgencies: Record<string, string>;
}

export default function AnnouncementCreate({ condominiums, categories, urgencies }: Props) {
    const { auth } = usePage<PageProps>().props;
    const perms = auth.user?.permissions ?? [];
    const canPublish = perms.includes('*') || perms.includes('announcements:publish');

    const form = useForm<AnnouncementFormData>({
        condominium_id: condominiums.length === 1 ? condominiums[0].value : '',
        title: '', body: '', category: 'general', urgency: 'normal', publish_at: '', expires_at: '',
    });

    const submit = (action: 'draft' | 'publish') => {
        form.transform(d => ({ ...d, action }));
        form.post(route('announcements.store'));
    };

    return (
        <AppLayout>
            <Head title="Novo Comunicado" />
            <div className="space-y-4">
                <div>
                    <Link href={route('announcements.index')} className="text-sm text-gray-500 hover:text-gray-700">← Comunicados</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Novo Comunicado</h1>
                </div>
                <AnnouncementForm
                    data={form.data} setData={form.setData} errors={form.errors} processing={form.processing}
                    onSubmit={submit} condominiums={condominiums} categories={categories} urgencies={urgencies}
                    canPublish={canPublish} backHref={route('announcements.index')}
                />
            </div>
        </AppLayout>
    );
}
