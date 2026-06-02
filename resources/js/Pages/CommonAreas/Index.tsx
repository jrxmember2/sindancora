import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { LayoutGrid, Plus, Pencil, Trash2, CalendarPlus, CalendarRange } from 'lucide-react';
import type { PageProps } from '@/types';

interface Option { value: string; label: string }
interface Area {
    id: string; name: string; capacity: number | null; requires_approval: boolean; active: boolean;
    opening_time: string | null; closing_time: string | null; reservations_count: number;
    condominium: { id: string; name: string } | null;
}
interface Props {
    areas: { data: Area[] };
    condominiums: Option[];
    filters: { condominium_id?: string };
}

const hhmm = (t: string | null) => (t ? t.slice(0, 5) : null);

export default function CommonAreasIndex({ areas, condominiums, filters }: Props) {
    const { auth } = usePage<PageProps>().props;
    const perms = auth.user?.permissions ?? [];
    const can = (p: string) => perms.includes('*') || perms.includes(p);
    const canManage = can('reservations:approve');

    const apply = (extra: Record<string, string> = {}) =>
        router.get(route('areas.index'), { condominium_id: filters.condominium_id ?? '', ...extra }, { preserveState: true, replace: true });
    const destroy = (id: string, name: string) => {
        if (confirm(`Remover a área "${name}"?`)) router.delete(route('areas.destroy', id));
    };

    return (
        <AppLayout>
            <Head title="Áreas Comuns" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <LayoutGrid className="h-6 w-6 text-blue-600" />
                        <h1 className="text-2xl font-bold text-gray-900">Áreas Comuns</h1>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('reservations.index')} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                            <CalendarRange className="h-4 w-4" /> Reservas
                        </Link>
                        {canManage && (
                            <Link href={route('areas.create')} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700">
                                <Plus className="h-4 w-4" /> Nova Área
                            </Link>
                        )}
                    </div>
                </div>

                {condominiums.length > 1 && (
                    <select value={filters.condominium_id ?? ''} onChange={e => apply({ condominium_id: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">Todos os condomínios</option>
                        {condominiums.map(c => <option key={c.value} value={c.value}>{c.label}</option>)}
                    </select>
                )}

                <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b border-gray-100 bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Área</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Condomínio</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Horário</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Aprovação</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Reservas</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {areas.data.length === 0 && (
                                <tr><td colSpan={6} className="px-4 py-8 text-center text-sm text-gray-500">Nenhuma área comum cadastrada.</td></tr>
                            )}
                            {areas.data.map(a => (
                                <tr key={a.id} className="transition-colors hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <p className="font-medium text-gray-900">{a.name}{!a.active && <span className="ml-2 text-xs text-gray-400">(inativa)</span>}</p>
                                        {a.capacity && <p className="text-xs text-gray-400">Capacidade: {a.capacity}</p>}
                                    </td>
                                    <td className="px-4 py-3 text-xs text-gray-600">{a.condominium?.name ?? '—'}</td>
                                    <td className="px-4 py-3 text-xs text-gray-600">{hhmm(a.opening_time) && hhmm(a.closing_time) ? `${hhmm(a.opening_time)}–${hhmm(a.closing_time)}` : '—'}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${a.requires_approval ? 'bg-amber-50 text-amber-700' : 'bg-green-50 text-green-700'}`}>
                                            {a.requires_approval ? 'Exige' : 'Automática'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-xs text-gray-600">{a.reservations_count}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            {can('reservations:create') && a.active && (
                                                <Link href={route('reservations.create', { area: a.id })} title="Reservar" className="rounded p-1.5 text-gray-400 transition-colors hover:bg-blue-50 hover:text-blue-600"><CalendarPlus className="h-4 w-4" /></Link>
                                            )}
                                            {canManage && (
                                                <Link href={route('areas.edit', a.id)} className="rounded p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"><Pencil className="h-4 w-4" /></Link>
                                            )}
                                            {canManage && (
                                                <button onClick={() => destroy(a.id, a.name)} className="rounded p-1.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-500"><Trash2 className="h-4 w-4" /></button>
                                            )}
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
