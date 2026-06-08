import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Building2, Plus, Search, Upload, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Unit {
    id: string; number: string; floor: number | null; type: string; status: string;
    area_m2: number | null; block: { name: string } | null;
    active_links: { person: { name: string }; type: string; is_primary: boolean }[];
}
interface Block { id: string; name: string }
interface Props {
    condominium: { id: string; name: string };
    units: { data: Unit[]; meta: any };
    blocks: Block[];
    typeLabels: Record<string, string>;
    statusLabels: Record<string, string>;
    filters: Record<string, string>;
}

const STATUS_COLORS: Record<string, string> = {
    occupied: 'bg-green-100 text-green-700',
    vacant: 'bg-gray-100 text-gray-600',
    under_renovation: 'bg-amber-100 text-amber-700',
};

export default function UnitsIndex({ condominium, units, blocks, typeLabels, statusLabels, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [blockId, setBlockId] = useState(filters.block_id ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const apply = () => router.get(route('condominiums.units.index', condominium.id), { search, block_id: blockId, status }, { preserveState: true });

    const destroy = (unitId: string, number: string) => {
        if (confirm(`Excluir unidade ${number}?`)) router.delete(route('condominiums.units.destroy', [condominium.id, unitId]));
    };

    return (
        <AppLayout>
            <Head title={`Unidades — ${condominium.name}`} />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <Link href={route('condominiums.show', condominium.id)} className="text-sm text-gray-500 hover:text-gray-700">← {condominium.name}</Link>
                        <h1 className="mt-1 text-2xl font-bold text-gray-900">Unidades</h1>
                        <p className="text-sm text-gray-500">{units.data.length} unidade(s)</p>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('condominiums.units.import', condominium.id)} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                            <Upload className="h-4 w-4" /> Importar CSV
                        </Link>
                        <Link href={route('condominiums.units.create', condominium.id)} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                            <Plus className="h-4 w-4" /> Nova Unidade
                        </Link>
                    </div>
                </div>

                {/* Filtros */}
                <div className="flex flex-wrap gap-3">
                    <div className="relative flex-1 min-w-[200px]">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <input value={search} onChange={e => setSearch(e.target.value)} onKeyDown={e => e.key === 'Enter' && apply()} placeholder="Buscar número…" className="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                    </div>
                    {blocks.length > 0 && (
                        <select value={blockId} onChange={e => setBlockId(e.target.value)} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                            <option value="">Todos os blocos</option>
                            {blocks.map(b => <option key={b.id} value={b.id}>{b.name}</option>)}
                        </select>
                    )}
                    <select value={status} onChange={e => setStatus(e.target.value)} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="">Todos os status</option>
                        {Object.entries(statusLabels).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                    </select>
                    <button onClick={apply} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">Filtrar</button>
                </div>

                {/* Tabela */}
                <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b border-gray-100 bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Unidade</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Bloco / Andar</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Tipo</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Morador</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {units.data.length === 0 && (
                                <tr><td colSpan={6} className="px-4 py-8 text-center text-sm text-gray-500">Nenhuma unidade encontrada.</td></tr>
                            )}
                            {units.data.map(unit => {
                                const primary = unit.active_links.find(l => l.is_primary) ?? unit.active_links[0];
                                return (
                                    <tr key={unit.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-4 py-3 font-semibold text-gray-900">{unit.number}</td>
                                        <td className="px-4 py-3 text-gray-600">{unit.block?.name ?? '—'}{unit.floor != null ? ` · ${unit.floor}º` : ''}</td>
                                        <td className="px-4 py-3 text-gray-600">{typeLabels[unit.type] ?? unit.type}</td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[unit.status] ?? 'bg-gray-100 text-gray-600'}`}>
                                                {statusLabels[unit.status] ?? unit.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-gray-600 text-xs">
                                            {primary ? `${primary.person.name}` : <span className="text-gray-400">—</span>}
                                            {unit.active_links.length > 1 && <span className="ml-1 text-gray-400">+{unit.active_links.length - 1}</span>}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                <Link href={route('condominiums.units.edit', [condominium.id, unit.id])} className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                                                    <Pencil className="h-4 w-4" />
                                                </Link>
                                                <button onClick={() => destroy(unit.id, unit.number)} className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 transition-colors">
                                                    <Trash2 className="h-3.5 w-3.5" /> Excluir
                                                </button>
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
