import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Package, Check } from 'lucide-react';

interface ParcelRow {
    id: string;
    description: string;
    carrier: string | null;
    status: 'awaiting' | 'picked_up';
    condominium: string | null;
    unit: string | null;
    received_at: string | null;
    picked_up_at: string | null;
}
interface Paginator<T> { data: T[]; links: { url: string | null; label: string; active: boolean }[] }
interface Option { value: string; label: string }
interface Props {
    parcels: Paginator<ParcelRow>;
    condominiums: Option[];
    statuses: Record<string, string>;
    filters: { condominium_id: string | null; status: string | null };
}

function formatTime(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' });
}

export default function Index({ parcels, condominiums, statuses, filters }: Props) {
    const setFilter = (key: string, value: string) => {
        router.get(route('parcels.index'), { ...filters, [key]: value || undefined }, { preserveScroll: true, preserveState: true, replace: true });
    };

    const pickup = (id: string) => router.post(route('parcels.pickup', id), {}, { preserveScroll: true });

    return (
        <AppLayout>
            <Head title="Encomendas" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Encomendas</h1>
                    <p className="mt-1 text-sm text-gray-500">Acompanhamento das encomendas recebidas na portaria. O registro é feito pelo porteiro.</p>
                </div>

                <div className="flex flex-wrap gap-3">
                    <select value={filters.condominium_id ?? ''} onChange={(e) => setFilter('condominium_id', e.target.value)} className="rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Todos os condomínios</option>
                        {condominiums.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                    </select>
                    <select value={filters.status ?? ''} onChange={(e) => setFilter('status', e.target.value)} className="rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Todos os status</option>
                        {Object.entries(statuses).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                    </select>
                </div>

                <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-gray-100 text-sm">
                        <thead className="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Encomenda</th>
                                <th className="px-4 py-3">Condomínio / Unidade</th>
                                <th className="px-4 py-3">Recebida</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {parcels.data.length === 0 && (
                                <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">Nenhuma encomenda.</td></tr>
                            )}
                            {parcels.data.map((p) => (
                                <tr key={p.id}>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <Package className="h-4 w-4 text-gray-400" />
                                            <div>
                                                <p className="font-medium text-gray-900">{p.description}</p>
                                                {p.carrier && <p className="text-xs text-gray-500">{p.carrier}</p>}
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-gray-600">{p.condominium} — un. {p.unit}</td>
                                    <td className="px-4 py-3 text-gray-600">{formatTime(p.received_at)}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${p.status === 'awaiting' ? 'bg-amber-50 text-amber-700' : 'bg-green-50 text-green-700'}`}>
                                            {statuses[p.status]}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {p.status === 'awaiting' && (
                                            <button onClick={() => pickup(p.id)} className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                                <Check className="h-4 w-4" /> Dar baixa
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {parcels.links.length > 3 && (
                    <div className="flex flex-wrap gap-1">
                        {parcels.links.map((l, i) => (
                            <Link
                                key={i}
                                href={l.url ?? '#'}
                                className={`rounded-lg px-3 py-1.5 text-sm ${l.active ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'} ${!l.url ? 'pointer-events-none text-gray-300' : ''}`}
                                dangerouslySetInnerHTML={{ __html: l.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
