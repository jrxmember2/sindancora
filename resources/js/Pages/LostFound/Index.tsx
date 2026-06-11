import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { PackageSearch, Plus, Check, Trash2 } from 'lucide-react';

interface Item {
    id: string;
    type: 'found' | 'lost';
    title: string;
    description: string | null;
    category: string | null;
    location: string | null;
    status: 'open' | 'resolved';
    condominium: string | null;
    occurred_on: string | null;
    photo: string | null;
}
interface Paginator<T> { data: T[]; links: { url: string | null; label: string; active: boolean }[] }
interface Props {
    items: Paginator<Item>;
    condominiums: { value: string; label: string }[];
    types: Record<string, string>;
    statuses: Record<string, string>;
    filters: { status: string | null; type: string | null };
}

export default function Index({ items, types, statuses, filters }: Props) {
    const setFilter = (key: string, value: string) => router.get(route('lost-found.index'), { ...filters, [key]: value || undefined }, { preserveScroll: true, preserveState: true, replace: true });
    const resolve = (id: string) => router.post(route('lost-found.resolve', id), {}, { preserveScroll: true });
    const destroy = (id: string) => { if (confirm('Excluir este item?')) router.delete(route('lost-found.destroy', id), { preserveScroll: true }); };

    return (
        <AppLayout>
            <Head title="Achados & Perdidos" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Achados & Perdidos</h1>
                        <p className="mt-1 text-sm text-gray-500">Itens achados ou perdidos no condomínio.</p>
                    </div>
                    <Link href={route('lost-found.create')} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        <Plus className="h-4 w-4" /> Novo item
                    </Link>
                </div>

                <div className="flex flex-wrap gap-3">
                    <select value={filters.type ?? ''} onChange={(e) => setFilter('type', e.target.value)} className="rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Achados e perdidos</option>
                        {Object.entries(types).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                    </select>
                    <select value={filters.status ?? ''} onChange={(e) => setFilter('status', e.target.value)} className="rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Todos os status</option>
                        {Object.entries(statuses).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                    </select>
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                    {items.data.length === 0 && <p className="col-span-full rounded-xl border border-gray-100 bg-white py-10 text-center text-sm text-gray-400">Nenhum item.</p>}
                    {items.data.map((it) => (
                        <div key={it.id} className="flex gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                            {it.photo ? (
                                <img src={route('attachments.download', it.photo)} alt={it.title} className="h-16 w-16 flex-shrink-0 rounded-lg object-cover" />
                            ) : (
                                <span className="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-300"><PackageSearch className="h-7 w-7" /></span>
                            )}
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2">
                                    <span className={`rounded-full px-2 py-0.5 text-[11px] font-medium ${it.type === 'found' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'}`}>{types[it.type]}</span>
                                    <span className={`rounded-full px-2 py-0.5 text-[11px] font-medium ${it.status === 'open' ? 'bg-gray-100 text-gray-600' : 'bg-blue-50 text-blue-700'}`}>{statuses[it.status]}</span>
                                </div>
                                <p className="mt-1 truncate font-medium text-gray-900">{it.title}</p>
                                <p className="truncate text-xs text-gray-500">{it.condominium}{it.location ? ` · ${it.location}` : ''}</p>
                                <div className="mt-2 flex gap-3">
                                    {it.status === 'open' && <button onClick={() => resolve(it.id)} className="inline-flex items-center gap-1 text-xs font-medium text-green-600 hover:text-green-700"><Check className="h-3.5 w-3.5" /> Resolver</button>}
                                    <button onClick={() => destroy(it.id)} className="inline-flex items-center gap-1 text-xs font-medium text-red-500 hover:text-red-600"><Trash2 className="h-3.5 w-3.5" /> Excluir</button>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {items.links.length > 3 && (
                    <div className="flex flex-wrap gap-1">
                        {items.links.map((l, i) => (
                            <Link key={i} href={l.url ?? '#'} className={`rounded-lg px-3 py-1.5 text-sm ${l.active ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'} ${!l.url ? 'pointer-events-none text-gray-300' : ''}`} dangerouslySetInnerHTML={{ __html: l.label }} />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
