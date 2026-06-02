import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';
import AnnouncementForm, { AnnouncementFormData } from './AnnouncementForm';

interface Option { value: string; label: string }
interface Announcement {
    id: string; condominium_id: string; title: string; body: string;
    category: string; urgency: string; status: string;
    publish_at: string | null; expires_at: string | null;
}
interface Props {
    announcement: Announcement;
    condominiums: Option[];
    categories: Record<string, string>;
    urgencies: Record<string, string>;
}

// ISO (UTC) → valor aceito por <input type="datetime-local"> (YYYY-MM-DDTHH:mm)
const toLocalInput = (iso: string | null): string => (iso ? iso.slice(0, 16) : '');

export default function AnnouncementEdit({ announcement, condominiums, categories, urgencies }: Props) {
    const { auth } = usePage<PageProps>().props;
    const perms = auth.user?.permissions ?? [];
    const canPublish = perms.includes('*') || perms.includes('announcements:publish');

    const form = useForm<AnnouncementFormData>({
        condominium_id: announcement.condominium_id,
        title: announcement.title,
        body: announcement.body,
        category: announcement.category,
        urgency: announcement.urgency,
        publish_at: toLocalInput(announcement.publish_at),
        expires_at: toLocalInput(announcement.expires_at),
    });

    const submit = (action: 'draft' | 'publish') => {
        form.transform(d => ({ ...d, action }));
        form.put(route('announcements.update', announcement.id));
    };

    return (
        <AppLayout>
            <Head title="Editar Comunicado" />
            <div className="space-y-4">
                <div>
                    <Link href={route('announcements.show', announcement.id)} className="text-sm text-gray-500 hover:text-gray-700">← Comunicado</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Editar Comunicado</h1>
                </div>
                <AnnouncementForm
                    data={form.data} setData={form.setData} errors={form.errors} processing={form.processing}
                    onSubmit={submit} condominiums={condominiums} categories={categories} urgencies={urgencies}
                    canPublish={canPublish} backHref={route('announcements.show', announcement.id)}
                />
            </div>
        </AppLayout>
    );
}
