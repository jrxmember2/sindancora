import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Wrench, Plus, Pencil, Trash2, AlertTriangle } from 'lucide-react';
import type { PageProps } from '@/types';

interface Option { value: string; label: string }
interface Plan {
    id: string; title: string; category: string | null; frequency: string;
    next_due_date: string | null; days_until_due: number | null; status: string | null;
    is_active: boolean; records_count: number;
    condominium: { id: string; name: string } | null;
    supplier: { id: string; name: string } | null;
}
interface Props {
    plans: { data: Plan[]; links: { url: string | null; label: string; active: boolean }[] };
    overdueCount: number;
    categories: Record<string, string>;
    condominiums: Option[];
    frequencies: Record<string, string>;
    filters: { condominium_id?: string; category?: string; status?: string };
}

function fmtDate(iso: string | null) {
    return iso ? new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—';
}

function StatusBadge({ status, days }: { status: string | null; days: number | null }) {
    if (status === 'overdue') return <span className="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">Atrasada {days !== null ? `${Math.abs(days)}d` : ''}</span>;
    if (status === 'due_soon') return <span className="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">{days === 0 ? 'Vence hoje' : `Vence em ${days}d`}</span>;
    if (status === 'ok') return <span className="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Em dia</span>;
    return <span className="text-xs text-gray-400">—</span>;
}

export default function MaintenanceIndex({ plans, overdueCount, categories, condominiums, frequencies, filters }: Props) {
    const { auth } = usePage<PageProps>().props;
    const perms = auth.user?.permissions ?? [];
    const can = (p: string) => perms.includes('*') || perms.includes(p);

    const apply = (extra: Record<string, string> = {}) =>
        router.get(route('maintenance.index'),
            { condominium_id: filters.condominium_id ?? '', category: filters.category ?? '', status: filters.status ?? '', ...extra },
            { preserveState: true, replace: true });

    const destroy = (id: string, title: string) => {
        if (confirm(`Remover a manutenção "${title}"?`)) router.delete(route('maintenance.destroy', id));
    };

    return (
        <AppLayout>
            <Head title="Manutenção" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Wrench className="h-6 w-6 text-blue-600" />
                        <h1 className="text-2xl font-bold text-gray-900">Manutenção preventiva</h1>
                    </div>
                    {can('maintenance:create') && (
                        <Link href={route('maintenance.create')} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700">
                            <Plus className="h-4 w-4" /> Nova Manutenção
                        </Link>
                    )}
                </div>

                {overdueCount > 0 && (
                    <div className="flex items-center gap-2 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <AlertTriangle className="h-4 w-4" />
                        {overdueCount} manutenção(ões) atrasada(s).
                    </div>
                )}

                <div className="flex flex-wrap gap-2">
                    <select value={filters.status ?? ''} onChange={e => apply({ status: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">Todas as situações</option>
                        <option value="overdue">Atrasadas</option>
                        <option value="due_soon">Próximas (em alerta)</option>
                    </select>
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
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Manutenção</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Condomínio</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Recorrência</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Próxima</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Situação</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {plans.data.length === 0 && (
                                <tr><td colSpan={6} className="px-4 py-8 text-center text-sm text-gray-500">Nenhuma manutenção cadastrada.</td></tr>
                            )}
                            {plans.data.map(p => (
                                <tr key={p.id} className="transition-colors hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <Link href={route('maintenance.show', p.id)} className="font-medium text-gray-900 hover:text-blue-600">{p.title}</Link>
                                        {!p.is_active && <span className="ml-2 text-xs text-gray-400">(inativa)</span>}
                                        <p className="text-xs text-gray-400">{p.category ? (categories[p.category] ?? p.category) : '—'}{p.supplier ? ` · ${p.supplier.name}` : ''}</p>
                                    </td>
                                    <td className="px-4 py-3 text-xs text-gray-600">{p.condominium?.name ?? '—'}</td>
                                    <td className="px-4 py-3 text-xs text-gray-600">{frequencies[p.frequency] ?? p.frequency}</td>
                                    <td className="px-4 py-3 text-xs text-gray-600">{fmtDate(p.next_due_date)}</td>
                                    <td className="px-4 py-3"><StatusBadge status={p.status} days={p.days_until_due} /></td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            {can('maintenance:update') && (
                                                <Link href={route('maintenance.edit', p.id)} className="rounded p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"><Pencil className="h-4 w-4" /></Link>
                                            )}
                                            {can('maintenance:delete') && (
                                                <button onClick={() => destroy(p.id, p.title)} className="rounded p-1.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-500"><Trash2 className="h-4 w-4" /></button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {plans.links.length > 3 && (
                    <div className="flex flex-wrap gap-1">
                        {plans.links.map((l, i) => (
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
