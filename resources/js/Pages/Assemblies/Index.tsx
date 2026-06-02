import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Vote, Plus } from 'lucide-react';

interface Row {
    id: string; title: string; status: string; scheduled_at: string | null;
    condominium: { name: string } | null;
}
interface Paginated<T> { data: T[]; links: { url: string | null; label: string; active: boolean }[] }
interface Option { value: string; label: string }
interface Props {
    assemblies: Paginated<Row>;
    condominiums: Option[];
    statuses: Record<string, string>;
    filters: { condominium_id?: string; status?: string };
}

const statusStyles: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-600',
    open: 'bg-green-100 text-green-700',
    closed: 'bg-blue-100 text-blue-700',
};

export default function AssembliesIndex({ assemblies, condominiums, statuses, filters }: Props) {
    const filter = (key: string, value: string) =>
        router.get(route('assemblies.index'), { ...filters, [key]: value || undefined }, { preserveState: true, replace: true });

    const field = 'rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';

    return (
        <AppLayout>
            <Head title="Assembleias" />

            <div className="mb-4 flex items-center justify-between">
                <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900"><Vote className="h-6 w-6" /> Assembleias</h1>
                <Link href={route('assemblies.create')} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    <Plus className="h-4 w-4" /> Nova assembleia
                </Link>
            </div>

            <div className="mb-4 flex flex-wrap gap-2">
                <select value={filters.condominium_id ?? ''} onChange={(e) => filter('condominium_id', e.target.value)} className={field}>
                    <option value="">Todos os condomínios</option>
                    {condominiums.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                </select>
                <select value={filters.status ?? ''} onChange={(e) => filter('status', e.target.value)} className={field}>
                    <option value="">Todos os status</option>
                    {Object.entries(statuses).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
            </div>

            <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                {assemblies.data.length === 0 && <p className="px-4 py-10 text-center text-sm text-gray-400">Nenhuma assembleia.</p>}
                {assemblies.data.length > 0 && (
                    <table className="w-full text-sm">
                        <thead className="border-b border-gray-100 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th className="px-4 py-2">Título</th>
                                <th className="px-4 py-2">Condomínio</th>
                                <th className="px-4 py-2">Data</th>
                                <th className="px-4 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {assemblies.data.map((a) => (
                                <tr key={a.id} className="cursor-pointer hover:bg-gray-50" onClick={() => router.visit(route('assemblies.show', a.id))}>
                                    <td className="px-4 py-2 font-medium text-gray-900">{a.title}</td>
                                    <td className="px-4 py-2 text-gray-500">{a.condominium?.name ?? '—'}</td>
                                    <td className="px-4 py-2 text-gray-500">{a.scheduled_at ? new Date(a.scheduled_at).toLocaleString('pt-BR') : '—'}</td>
                                    <td className="px-4 py-2">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusStyles[a.status] ?? 'bg-gray-100 text-gray-600'}`}>{statuses[a.status] ?? a.status}</span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </AppLayout>
    );
}
