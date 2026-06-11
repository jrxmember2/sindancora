import PortalLayout from '@/Layouts/PortalLayout';
import { Head, Link } from '@inertiajs/react';
import { Newspaper, Plus } from 'lucide-react';

interface PostRow {
    id: string;
    post_type: 'notice' | 'classified';
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
    image: string | null;
}

const money = (value: number | null) => value === null ? null : value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function Index({ posts, types, categories }: { posts: PostRow[]; types: Record<string, string>; categories: Record<string, string> }) {
    return (
        <PortalLayout title="Mural">
            <Head title="Mural" />

            <Link href={route('portal.community-board.create')} className="mb-5 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                <Plus className="h-4 w-4" /> Enviar classificado
            </Link>

            <div className="space-y-3">
                {posts.length === 0 && <p className="rounded-xl border border-gray-100 bg-white py-10 text-center text-sm text-gray-400">Nenhuma publicacao no mural.</p>}
                {posts.map((post) => (
                    <div key={post.id} className="overflow-hidden rounded-xl border border-gray-100 bg-white">
                        {post.image && <img src={route('attachments.download', post.image)} alt={post.title} className="h-48 w-full object-cover" />}
                        <div className="p-4">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-700">{types[post.post_type]}</span>
                                {post.category && <span className="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700">{categories[post.category] ?? post.category}</span>}
                                {money(post.price) && <span className="rounded-full bg-green-50 px-2 py-0.5 text-[11px] font-medium text-green-700">{money(post.price)}</span>}
                            </div>
                            <div className="mt-2 flex items-start gap-3">
                                {!post.image && <span className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-300"><Newspaper className="h-5 w-5" /></span>}
                                <div className="min-w-0 flex-1">
                                    <h2 className="font-semibold text-gray-900">{post.title}</h2>
                                    <p className="mt-1 whitespace-pre-wrap text-sm leading-6 text-gray-600">{post.body}</p>
                                    <p className="mt-3 text-xs text-gray-400">{post.condominium}{post.author ? ` - ${post.author}` : ''}</p>
                                    {(post.contact_name || post.contact_phone || post.contact_email) && (
                                        <p className="mt-1 text-xs text-gray-500">
                                            Contato: {[post.contact_name, post.contact_phone, post.contact_email].filter(Boolean).join(' - ')}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </PortalLayout>
    );
}
