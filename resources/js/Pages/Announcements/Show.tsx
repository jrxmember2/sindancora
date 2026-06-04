import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Pencil, Trash2, Send, Calendar, Clock, Building2, User, Paperclip } from 'lucide-react';
import type { PageProps } from '@/types';
import AttachmentList, { Attachment } from '@/Components/AttachmentList';

interface Announcement {
    id: string; title: string; body: string; category: string; urgency: string; status: string;
    published_at: string | null; publish_at: string | null; expires_at: string | null;
    condominium: { id: string; name: string } | null;
    creator: { id: string; name: string } | null;
}
interface Props {
    announcement: Announcement;
    attachments: Attachment[];
    categories: Record<string, string>;
    urgencies: Record<string, string>;
}

const fmt = (iso: string | null) => (iso ? new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : null);

const urgencyStyle: Record<string, string> = {
    low: 'bg-gray-100 text-gray-600',
    normal: 'bg-blue-50 text-blue-700',
    high: 'bg-red-50 text-red-700',
};

export default function AnnouncementShow({ announcement: a, attachments, categories, urgencies }: Props) {
    const { auth } = usePage<PageProps>().props;
    const perms = auth.user?.permissions ?? [];
    const can = (p: string) => perms.includes('*') || perms.includes(p);

    const isScheduled = a.status === 'draft' && a.publish_at && new Date(a.publish_at) > new Date();
    const statusLabel = a.status === 'published' ? 'Publicado' : isScheduled ? 'Agendado' : 'Rascunho';
    const statusClass = a.status === 'published' ? 'bg-green-50 text-green-700' : isScheduled ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-600';

    const publish = () => {
        if (confirm('Publicar agora? Os moradores com e-mail cadastrado serão notificados.')) {
            router.post(route('announcements.publish', a.id));
        }
    };
    const destroy = () => {
        if (confirm('Excluir este comunicado?')) router.delete(route('announcements.destroy', a.id));
    };

    return (
        <AppLayout>
            <Head title={a.title} />
            <div className="mx-auto max-w-3xl space-y-4">
                <div className="flex items-center justify-between">
                    <Link href={route('announcements.index')} className="text-sm text-gray-500 hover:text-gray-700">← Comunicados</Link>
                    <div className="flex gap-2">
                        {a.status === 'draft' && can('announcements:publish') && (
                            <button onClick={publish} className="inline-flex items-center gap-2 rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-green-700">
                                <Send className="h-4 w-4" /> Publicar agora
                            </button>
                        )}
                        {can('announcements:update') && (
                            <Link href={route('announcements.edit', a.id)} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                                <Pencil className="h-4 w-4" /> Editar
                            </Link>
                        )}
                        {can('announcements:delete') && (
                            <button onClick={destroy} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-red-600 transition-colors hover:bg-red-50">
                                <Trash2 className="h-4 w-4" /> Excluir
                            </button>
                        )}
                    </div>
                </div>

                <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div className="mb-3 flex flex-wrap items-center gap-2">
                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusClass}`}>{statusLabel}</span>
                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${urgencyStyle[a.urgency] ?? ''}`}>{urgencies[a.urgency] ?? a.urgency}</span>
                        <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{categories[a.category] ?? a.category}</span>
                    </div>

                    <h1 className="text-2xl font-bold text-gray-900">{a.title}</h1>

                    <div className="mt-3 flex flex-wrap gap-x-5 gap-y-1 border-b border-gray-100 pb-4 text-xs text-gray-500">
                        {a.condominium && <span className="inline-flex items-center gap-1"><Building2 className="h-3.5 w-3.5" /> {a.condominium.name}</span>}
                        {a.creator && <span className="inline-flex items-center gap-1"><User className="h-3.5 w-3.5" /> {a.creator.name}</span>}
                        {a.published_at && <span className="inline-flex items-center gap-1"><Calendar className="h-3.5 w-3.5" /> Publicado em {fmt(a.published_at)}</span>}
                        {isScheduled && <span className="inline-flex items-center gap-1 text-amber-600"><Clock className="h-3.5 w-3.5" /> Agendado para {fmt(a.publish_at)}</span>}
                        {a.expires_at && <span className="inline-flex items-center gap-1"><Clock className="h-3.5 w-3.5" /> Expira em {fmt(a.expires_at)}</span>}
                    </div>

                    <div className="rich-content mt-4" dangerouslySetInnerHTML={{ __html: a.body }} />

                    {attachments.length > 0 && (
                        <div className="mt-5 border-t border-gray-100 pt-4">
                            <p className="mb-2 flex items-center gap-1.5 text-sm font-medium text-gray-700">
                                <Paperclip className="h-4 w-4 text-gray-400" /> Anexos
                            </p>
                            <AttachmentList attachments={attachments} canRemove={can('announcements:update')} />
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
