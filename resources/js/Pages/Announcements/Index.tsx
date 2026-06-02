import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Megaphone, Plus, Search, Eye, Pencil, Trash2, Send } from 'lucide-react';
import { useState } from 'react';
import type { PageProps } from '@/types';

interface Option { value: string; label: string }
interface Announcement {
    id: string; title: string; category: string; urgency: string; status: string;
    published_at: string | null; publish_at: string | null; expires_at: string | null;
    condominium: { id: string; name: string } | null;
}
interface Props {
    announcements: { data: Announcement[] };
    condominiums: Option[];
    categories: Record<string, string>;
    urgencies: Record<string, string>;
    filters: { search?: string; status?: string; condominium_id?: string };
}

const urgencyStyle: Record<string, string> = {
    low: 'bg-gray-100 text-gray-600',
    normal: 'bg-blue-50 text-blue-700',
    high: 'bg-red-50 text-red-700',
};

function statusBadge(a: Announcement): { label: string; className: string } {
    if (a.status === 'draft') {
        if (a.publish_at && new Date(a.publish_at) > new Date()) {
            return { label: 'Agendado', className: 'bg-amber-50 text-amber-700' };
        }
        return { label: 'Rascunho', className: 'bg-gray-100 text-gray-600' };
    }
    if (a.expires_at && new Date(a.expires_at) < new Date()) {
        return { label: 'Expirado', className: 'bg-gray-100 text-gray-500' };
    }
    return { label: 'Publicado', className: 'bg-green-50 text-green-700' };
}

export default function AnnouncementsIndex({ announcements, condominiums, categories, urgencies, filters }: Props) {
    const { auth } = usePage<PageProps>().props;
    const perms = auth.user?.permissions ?? [];
    const can = (p: string) => perms.includes('*') || perms.includes(p);

    const [search, setSearch] = useState(filters.search ?? '');
    const apply = (extra: Record<string, string> = {}) =>
        router.get(route('announcements.index'), { search, status: filters.status ?? '', condominium_id: filters.condominium_id ?? '', ...extra }, { preserveState: true, replace: true });

    const destroy = (id: string, title: string) => {
        if (confirm(`Excluir o comunicado "${title}"?`)) router.delete(route('announcements.destroy', id));
    };
    const publish = (id: string, title: string) => {
        if (confirm(`Publicar "${title}" agora? Os moradores com e-mail cadastrado serão notificados.`)) {
            router.post(route('announcements.publish', id));
        }
    };

    return (
        <AppLayout>
            <Head title="Comunicados" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Megaphone className="h-6 w-6 text-blue-600" />
                        <h1 className="text-2xl font-bold text-gray-900">Comunicados</h1>
                    </div>
                    {can('announcements:create') && (
                        <Link href={route('announcements.create')} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700">
                            <Plus className="h-4 w-4" /> Novo Comunicado
                        </Link>
                    )}
                </div>

                <div className="flex flex-wrap gap-3">
                    <div className="relative max-w-sm flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input value={search} onChange={e => setSearch(e.target.value)} onKeyDown={e => e.key === 'Enter' && apply()} placeholder="Buscar por título…" className="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                    </div>
                    <select value={filters.status ?? ''} onChange={e => apply({ status: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">Todos os status</option>
                        <option value="draft">Rascunho / Agendado</option>
                        <option value="published">Publicado</option>
                    </select>
                    {condominiums.length > 1 && (
                        <select value={filters.condominium_id ?? ''} onChange={e => apply({ condominium_id: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                            <option value="">Todos os condomínios</option>
                            {condominiums.map(c => <option key={c.value} value={c.value}>{c.label}</option>)}
                        </select>
                    )}
                </div>

                <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b border-gray-100 bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Título</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Condomínio</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Categoria</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Urgência</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {announcements.data.length === 0 && (
                                <tr><td colSpan={6} className="px-4 py-8 text-center text-sm text-gray-500">Nenhum comunicado encontrado.</td></tr>
                            )}
                            {announcements.data.map(a => {
                                const badge = statusBadge(a);
                                return (
                                    <tr key={a.id} className="transition-colors hover:bg-gray-50">
                                        <td className="px-4 py-3 font-medium text-gray-900">{a.title}</td>
                                        <td className="px-4 py-3 text-gray-600">{a.condominium?.name ?? '—'}</td>
                                        <td className="px-4 py-3 text-gray-600">{categories[a.category] ?? a.category}</td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${urgencyStyle[a.urgency] ?? ''}`}>{urgencies[a.urgency] ?? a.urgency}</span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${badge.className}`}>{badge.label}</span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                {a.status === 'draft' && can('announcements:publish') && (
                                                    <button onClick={() => publish(a.id, a.title)} title="Publicar agora" className="rounded p-1.5 text-gray-400 transition-colors hover:bg-green-50 hover:text-green-600"><Send className="h-4 w-4" /></button>
                                                )}
                                                <Link href={route('announcements.show', a.id)} className="rounded p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"><Eye className="h-4 w-4" /></Link>
                                                {can('announcements:update') && (
                                                    <Link href={route('announcements.edit', a.id)} className="rounded p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"><Pencil className="h-4 w-4" /></Link>
                                                )}
                                                {can('announcements:delete') && (
                                                    <button onClick={() => destroy(a.id, a.title)} className="rounded p-1.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-500"><Trash2 className="h-4 w-4" /></button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
