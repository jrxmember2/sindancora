import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Truck, Plus, Pencil, Trash2, Star } from 'lucide-react';
import { maskCpfCnpj } from '@/lib/masks';
import type { PageProps } from '@/types';

interface Option { value: string; label: string }
interface Supplier {
    id: string; name: string; category: string | null; document: string | null;
    contact_name: string | null; phone: string | null; is_active: boolean;
    evaluations_count: number; evaluations_avg_score: number | null;
}
interface Props {
    suppliers: { data: Supplier[]; links: { url: string | null; label: string; active: boolean }[] };
    categories: Record<string, string>;
    condominiums: Option[];
    filters: { category?: string; condominium_id?: string; search?: string };
}

function Stars({ avg, count }: { avg: number | null; count: number }) {
    if (!count || avg === null) return <span className="text-xs text-gray-400">sem avaliação</span>;
    const rounded = Math.round(avg);
    return (
        <span className="inline-flex items-center gap-1" title={`${Number(avg).toFixed(1)} (${count})`}>
            {[1, 2, 3, 4, 5].map(i => (
                <Star key={i} className={`h-3.5 w-3.5 ${i <= rounded ? 'fill-amber-400 text-amber-400' : 'text-gray-300'}`} />
            ))}
            <span className="ml-1 text-xs text-gray-500">{Number(avg).toFixed(1)} ({count})</span>
        </span>
    );
}

export default function SuppliersIndex({ suppliers, categories, condominiums, filters }: Props) {
    const { auth } = usePage<PageProps>().props;
    const perms = auth.user?.permissions ?? [];
    const can = (p: string) => perms.includes('*') || perms.includes(p);

    const apply = (extra: Record<string, string> = {}) =>
        router.get(route('suppliers.index'),
            { category: filters.category ?? '', condominium_id: filters.condominium_id ?? '', search: filters.search ?? '', ...extra },
            { preserveState: true, replace: true });

    const destroy = (id: string, name: string) => {
        if (confirm(`Remover o fornecedor "${name}"?`)) router.delete(route('suppliers.destroy', id));
    };

    return (
        <AppLayout>
            <Head title="Fornecedores" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Truck className="h-6 w-6 text-blue-600" />
                        <h1 className="text-2xl font-bold text-gray-900">Fornecedores</h1>
                    </div>
                    {can('suppliers:create') && (
                        <Link href={route('suppliers.create')} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700">
                            <Plus className="h-4 w-4" /> Novo Fornecedor
                        </Link>
                    )}
                </div>

                <div className="flex flex-wrap gap-2">
                    <input
                        defaultValue={filters.search ?? ''}
                        onKeyDown={e => { if (e.key === 'Enter') apply({ search: (e.target as HTMLInputElement).value }); }}
                        placeholder="Buscar por nome ou CPF/CNPJ…"
                        className="w-64 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                    />
                    <select value={filters.category ?? ''} onChange={e => apply({ category: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">Todas as categorias</option>
                        {Object.entries(categories).map(([slug, label]) => <option key={slug} value={slug}>{label}</option>)}
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
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Fornecedor</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Categoria</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Contato</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Avaliação</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {suppliers.data.length === 0 && (
                                <tr><td colSpan={5} className="px-4 py-8 text-center text-sm text-gray-500">Nenhum fornecedor cadastrado.</td></tr>
                            )}
                            {suppliers.data.map(s => (
                                <tr key={s.id} className="transition-colors hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <Link href={route('suppliers.show', s.id)} className="font-medium text-gray-900 hover:text-blue-600">{s.name}</Link>
                                        {!s.is_active && <span className="ml-2 text-xs text-gray-400">(inativo)</span>}
                                        {s.document && <p className="text-xs text-gray-400">{maskCpfCnpj(s.document)}</p>}
                                    </td>
                                    <td className="px-4 py-3 text-xs text-gray-600">{s.category ? (categories[s.category] ?? s.category) : '—'}</td>
                                    <td className="px-4 py-3 text-xs text-gray-600">
                                        {s.contact_name ?? '—'}
                                        {s.phone && <p className="text-gray-400">{s.phone}</p>}
                                    </td>
                                    <td className="px-4 py-3"><Stars avg={s.evaluations_avg_score} count={s.evaluations_count} /></td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            {can('suppliers:update') && (
                                                <Link href={route('suppliers.edit', s.id)} className="rounded p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"><Pencil className="h-4 w-4" /></Link>
                                            )}
                                            {can('suppliers:delete') && (
                                                <button onClick={() => destroy(s.id, s.name)} className="rounded p-1.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-500"><Trash2 className="h-4 w-4" /></button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {suppliers.links.length > 3 && (
                    <div className="flex flex-wrap gap-1">
                        {suppliers.links.map((l, i) => (
                            <button key={i} disabled={!l.url} onClick={() => l.url && router.visit(l.url)}
                                className={`rounded px-3 py-1.5 text-sm ${l.active ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'} disabled:opacity-40`}
                                dangerouslySetInnerHTML={{ __html: l.label }} />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
