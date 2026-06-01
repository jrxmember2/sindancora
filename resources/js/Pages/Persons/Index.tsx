import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Users, Plus, Search, Eye, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Link2 { unit: { number: string; condominium: { name: string } }; type: string }
interface Person { id: string; name: string; cpf: string | null; email: string | null; phone: string | null; active_links: Link2[] }
interface Props { persons: { data: Person[]; meta: any }; filters: { search?: string } }

export default function PersonsIndex({ persons, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const apply = () => router.get(route('persons.index'), { search }, { preserveState: true });
    const destroy = (id: string, name: string) => {
        if (confirm(`Excluir "${name}"?`)) router.delete(route('persons.destroy', id));
    };

    return (
        <AppLayout>
            <Head title="Pessoas" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Pessoas</h1>
                        <p className="mt-1 text-sm text-gray-500">{persons.data.length} pessoa(s) cadastrada(s)</p>
                    </div>
                    <Link href={route('persons.create')} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                        <Plus className="h-4 w-4" /> Nova Pessoa
                    </Link>
                </div>

                <div className="flex gap-3">
                    <div className="relative flex-1 max-w-sm">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <input value={search} onChange={e => setSearch(e.target.value)} onKeyDown={e => e.key === 'Enter' && apply()} placeholder="Buscar por nome, CPF ou e-mail…" className="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                    </div>
                    <button onClick={apply} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">Buscar</button>
                </div>

                <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b border-gray-100 bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Nome</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">CPF</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Contato</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Vínculos</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {persons.data.length === 0 && (
                                <tr><td colSpan={5} className="px-4 py-8 text-center text-sm text-gray-500">Nenhuma pessoa cadastrada.</td></tr>
                            )}
                            {persons.data.map(p => (
                                <tr key={p.id} className="hover:bg-gray-50 transition-colors">
                                    <td className="px-4 py-3 font-medium text-gray-900">{p.name}</td>
                                    <td className="px-4 py-3 text-gray-600 font-mono text-xs">{p.cpf ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-600 text-xs">
                                        {p.email && <p>{p.email}</p>}
                                        {p.phone && <p>{p.phone}</p>}
                                        {!p.email && !p.phone && '—'}
                                    </td>
                                    <td className="px-4 py-3 text-xs text-gray-600">
                                        {p.active_links.length === 0 ? <span className="text-gray-400">Sem vínculo</span> : p.active_links.map((l, i) => (
                                            <span key={i} className="block">{l.unit.condominium.name} · {l.unit.number}</span>
                                        ))}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <Link href={route('persons.show', p.id)} className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"><Eye className="h-4 w-4" /></Link>
                                            <Link href={route('persons.edit', p.id)} className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"><Pencil className="h-4 w-4" /></Link>
                                            <button onClick={() => destroy(p.id, p.name)} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-500 transition-colors"><Trash2 className="h-4 w-4" /></button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
