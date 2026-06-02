import PortalLayout from '@/Layouts/PortalLayout';
import { Head, Link } from '@inertiajs/react';
import { Megaphone, ChevronRight } from 'lucide-react';

interface Announcement {
    id: string; title: string; category: string; urgency: string; published_at: string;
    is_read: boolean; condominium: { name: string } | null;
}
interface Props {
    announcements: { data: Announcement[] };
    categories: Record<string, string>;
    urgencies: Record<string, string>;
}

export default function PortalAnnouncements({ announcements, categories }: Props) {
    return (
        <PortalLayout title="Comunicados">
            <Head title="Comunicados" />

            <div className="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                {announcements.data.length === 0 && (
                    <div className="px-4 py-10 text-center">
                        <Megaphone className="mx-auto h-8 w-8 text-gray-300" />
                        <p className="mt-2 text-sm text-gray-400">Nenhum comunicado disponível.</p>
                    </div>
                )}
                {announcements.data.map((a) => (
                    <Link key={a.id} href={route('portal.announcements.show', a.id)} className="flex items-center gap-3 px-4 py-3.5 transition-colors hover:bg-gray-50">
                        {!a.is_read && <span className="h-2 w-2 flex-shrink-0 rounded-full bg-blue-500" title="Não lido" />}
                        <div className={`min-w-0 flex-1 ${a.is_read ? 'pl-5' : ''}`}>
                            <div className="flex items-center gap-2">
                                <p className={`truncate text-sm ${a.is_read ? 'font-medium text-gray-700' : 'font-semibold text-gray-900'}`}>{a.title}</p>
                                {a.urgency === 'high' && <span className="rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-semibold text-red-700">Urgente</span>}
                            </div>
                            <p className="text-xs text-gray-500">
                                {a.condominium?.name ? `${a.condominium.name} · ` : ''}{categories[a.category] ?? a.category} · {new Date(a.published_at).toLocaleDateString('pt-BR')}
                            </p>
                        </div>
                        <ChevronRight className="h-4 w-4 flex-shrink-0 text-gray-300" />
                    </Link>
                ))}
            </div>
        </PortalLayout>
    );
}
