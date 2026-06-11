import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Check, Newspaper, Plus, Trash2, XCircle, Archive } from 'lucide-react';

interface PostRow {
    id: string;
    post_type: 'notice' | 'classified';
    status: 'pending' | 'published' | 'rejected' | 'archived';
    category: string | null;
    title: string;
    body: string;
    condominium: string | null;
    author: string | null;
    price: number | null;
    contact_name: string | null;
    contact_phone: string | null;
    contact_email: string | null;
    published_at: string | null;
    expires_at: string | null;
    rejection_reason: string | null;
    image: string | null;
}

interface Paginator<T> { data: T[]; links: { url: string | null; label: string; active: boolean }[] }
interface Props {
    posts: Paginator<PostRow>;
    types: Record<string, string>;
    statuses: Record<string, string>;
    categories: Record<string, string>;
    filters: { post_type: string | null; status: string | null; search: string | null };
}

const badge: Record<string, string> = {
    pending: 'bg-amber-50 text-amber-700',
    published: 'bg-green-50 text-green-700',
    rejected: 'bg-red-50 text-red-700',
    archived: 'bg-gray-100 text-gray-600',
};

const money = (value: number | null) => value === null ? null : value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function Index({ posts, types, statuses, categories, filters }: Props) {
    const setFilter = (key: string, value: string) => router.get(route('community-board.index'), { ...filters, [key]: value || undefined }, { preserveScroll: true, preserveState: true, replace: true });
    const reject = (id: string) => {
        const reason = prompt('Motivo da rejeicao (opcional):');
        if (reason === null) return;
        router.post(route('community-board.reject', id), { reason }, { preserveScroll: true });
    };
    const destroy = (id: string) => {
        if (confirm('Remover esta publicacao?')) router.delete(route('community-board.destroy', id), { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title="Mural e classificados" />
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Mural e classificados</h1>
                        <p className="mt-1 text-sm text-gray-500">Publicacoes oficiais e classificados de moradores com moderacao.</p>
                    </div>
                    <Link href={route('community-board.create')} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        <Plus className="h-4 w-4" /> Nova publicacao
                    </Link>
                </div>

                <div className="flex flex-wrap gap-3">
                    <input value={filters.search ?? ''} onChange={(e) => setFilter('search', e.target.value)} className="w-64 rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Buscar por titulo" />
                    <select value={filters.post_type ?? ''} onChange={(e) => setFilter('post_type', e.target.value)} className="rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Todos os tipos</option>
                        {Object.entries(types).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
                    </select>
                    <select value={filters.status ?? ''} onChange={(e) => setFilter('status', e.target.value)} className="rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Todos os status</option>
                        {Object.entries(statuses).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
                    </select>
                </div>

                <div className="grid gap-3 lg:grid-cols-2">
                    {posts.data.length === 0 && <p className="col-span-full rounded-xl border border-gray-100 bg-white py-10 text-center text-sm text-gray-400">Nenhuma publicacao.</p>}
                    {posts.data.map((post) => (
                        <div key={post.id} className="flex gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                            {post.image ? (
                                <img src={route('attachments.download', post.image)} alt={post.title} className="h-20 w-20 flex-shrink-0 rounded-lg object-cover" />
                            ) : (
                                <span className="flex h-20 w-20 flex-shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-300"><Newspaper className="h-7 w-7" /></span>
                            )}
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-700">{types[post.post_type]}</span>
                                    <span className={`rounded-full px-2 py-0.5 text-[11px] font-medium ${badge[post.status]}`}>{statuses[post.status]}</span>
                                    {post.category && <span className="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700">{categories[post.category] ?? post.category}</span>}
                                </div>
                                <p className="mt-1 font-medium text-gray-900">{post.title}</p>
                                <p className="line-clamp-2 text-sm text-gray-500">{post.body}</p>
                                <p className="mt-1 text-xs text-gray-400">{post.condominium}{post.author ? ` - ${post.author}` : ''}{money(post.price) ? ` - ${money(post.price)}` : ''}</p>
                                {post.rejection_reason && <p className="mt-1 text-xs text-red-600">{post.rejection_reason}</p>}
                                <div className="mt-3 flex flex-wrap gap-3">
                                    {post.status !== 'published' && <button onClick={() => router.post(route('community-board.approve', post.id), {}, { preserveScroll: true })} className="inline-flex items-center gap-1 text-xs font-medium text-green-600 hover:text-green-700"><Check className="h-3.5 w-3.5" /> Aprovar</button>}
                                    {post.status === 'pending' && <button onClick={() => reject(post.id)} className="inline-flex items-center gap-1 text-xs font-medium text-red-600 hover:text-red-700"><XCircle className="h-3.5 w-3.5" /> Rejeitar</button>}
                                    {post.status === 'published' && <button onClick={() => router.post(route('community-board.archive', post.id), {}, { preserveScroll: true })} className="inline-flex items-center gap-1 text-xs font-medium text-gray-600 hover:text-gray-700"><Archive className="h-3.5 w-3.5" /> Arquivar</button>}
                                    <button onClick={() => destroy(post.id)} className="inline-flex items-center gap-1 text-xs font-medium text-red-500 hover:text-red-600"><Trash2 className="h-3.5 w-3.5" /> Excluir</button>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {posts.links.length > 3 && (
                    <div className="flex flex-wrap gap-1">
                        {posts.links.map((link, index) => (
                            <Link key={index} href={link.url ?? '#'} className={`rounded-lg px-3 py-1.5 text-sm ${link.active ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'} ${!link.url ? 'pointer-events-none text-gray-300' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
