import PortalLayout from '@/Layouts/PortalLayout';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Paperclip } from 'lucide-react';
import AttachmentList, { Attachment } from '@/Components/AttachmentList';

interface Announcement {
    id: string; title: string; body: string; category: string; urgency: string;
    published_at: string; expires_at: string | null;
    condominium: { name: string } | null; creator: { name: string } | null;
}
interface Props {
    announcement: Announcement;
    attachments: Attachment[];
    categories: Record<string, string>;
    urgencies: Record<string, string>;
}

export default function PortalAnnouncementShow({ announcement, attachments, categories }: Props) {
    return (
        <PortalLayout>
            <Head title={announcement.title} />

            <Link href={route('portal.announcements.index')} className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <ArrowLeft className="h-4 w-4" /> Comunicados
            </Link>

            <article className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <div className="border-b border-gray-100 p-5">
                    {announcement.urgency === 'high' && (
                        <span className="mb-2 inline-block rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-700">URGENTE</span>
                    )}
                    <h1 className="text-xl font-bold text-gray-900">{announcement.title}</h1>
                    <p className="mt-1 text-xs text-gray-500">
                        {announcement.condominium?.name ? `${announcement.condominium.name} · ` : ''}
                        {categories[announcement.category] ?? announcement.category} ·{' '}
                        {new Date(announcement.published_at).toLocaleDateString('pt-BR')}
                        {announcement.creator?.name ? ` · por ${announcement.creator.name}` : ''}
                    </p>
                </div>
                <div className="rich-content p-5 text-[15px] leading-relaxed text-gray-700" dangerouslySetInnerHTML={{ __html: announcement.body }} />
                {attachments.length > 0 && (
                    <div className="border-t border-gray-100 p-5">
                        <p className="mb-2 flex items-center gap-1.5 text-sm font-medium text-gray-700">
                            <Paperclip className="h-4 w-4 text-gray-400" /> Anexos
                        </p>
                        <AttachmentList attachments={attachments} />
                    </div>
                )}
                {announcement.expires_at && (
                    <p className="border-t border-gray-100 px-5 py-3 text-xs text-gray-400">
                        Válido até {new Date(announcement.expires_at).toLocaleDateString('pt-BR')}.
                    </p>
                )}
            </article>
        </PortalLayout>
    );
}
