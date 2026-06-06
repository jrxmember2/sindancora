import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { FileText, Plus, Search, Download, Pencil, Trash2, HardDrive } from 'lucide-react';
import { useState } from 'react';
import type { PageProps } from '@/types';

interface Option { value: string; label: string }
interface StorageObj { file_size_bytes: number; original_filename: string | null; mime_type: string | null }
interface Document {
    id: string; title: string; category: string; visibility: string; created_at: string;
    valid_until: string | null; expiry_status: 'valid' | 'expiring' | 'expired' | null; days_until_expiry: number | null;
    condominium: { id: string; name: string } | null;
    storage_object: StorageObj | null;
    uploader: { id: string; name: string } | null;
}
interface Usage { used_mb: number; quota_mb: number; percentage_used: number; is_near_limit: boolean; is_at_limit: boolean }
interface Props {
    documents: { data: Document[] };
    condominiums: Option[];
    categories: Record<string, string>;
    visibilities: Record<string, string>;
    usage: Usage;
    filters: { search?: string; category?: string; condominium_id?: string };
}

const visibilityStyle: Record<string, string> = {
    residents: 'bg-green-50 text-green-700',
    restricted: 'bg-gray-100 text-gray-600',
};

function ValidityBadge({ d }: { d: Document }) {
    if (!d.valid_until || d.expiry_status === null) return <span className="text-xs text-gray-400">—</span>;
    const days = d.days_until_expiry ?? 0;
    const label = d.expiry_status === 'expired'
        ? `Vencido há ${Math.abs(days)} dia(s)`
        : days === 0 ? 'Vence hoje' : `Vence em ${days} dia(s)`;
    const style = d.expiry_status === 'expired'
        ? 'bg-red-50 text-red-700'
        : d.expiry_status === 'expiring' ? 'bg-amber-50 text-amber-700' : 'bg-green-50 text-green-700';
    return <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${style}`}>{label}</span>;
}

function formatBytes(bytes: number): string {
    if (!bytes) return '—';
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export default function DocumentsIndex({ documents, condominiums, categories, visibilities, usage, filters }: Props) {
    const { auth } = usePage<PageProps>().props;
    const perms = auth.user?.permissions ?? [];
    const can = (p: string) => perms.includes('*') || perms.includes(p);

    const [search, setSearch] = useState(filters.search ?? '');
    const apply = (extra: Record<string, string> = {}) =>
        router.get(route('documents.index'), {
            search, category: filters.category ?? '', condominium_id: filters.condominium_id ?? '', ...extra,
        }, { preserveState: true, replace: true });

    const destroy = (id: string, title: string) => {
        if (confirm(`Remover o documento "${title}"? O arquivo vai para a lixeira por 30 dias.`)) router.delete(route('documents.destroy', id));
    };

    const barColor = usage.is_at_limit ? 'bg-red-500' : usage.is_near_limit ? 'bg-amber-500' : 'bg-blue-500';

    return (
        <AppLayout>
            <Head title="Documentos" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <FileText className="h-6 w-6 text-blue-600" />
                        <h1 className="text-2xl font-bold text-gray-900">Documentos</h1>
                    </div>
                    {can('documents:upload') && (
                        <Link href={route('documents.create')} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700">
                            <Plus className="h-4 w-4" /> Enviar Documento
                        </Link>
                    )}
                </div>

                {/* Uso de armazenamento */}
                <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                    <div className="mb-2 flex items-center justify-between text-sm">
                        <span className="inline-flex items-center gap-2 font-medium text-gray-700"><HardDrive className="h-4 w-4 text-gray-400" /> Armazenamento</span>
                        <span className="text-gray-500">{usage.used_mb} MB de {usage.quota_mb} MB ({usage.percentage_used}%)</span>
                    </div>
                    <div className="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                        <div className={`h-full ${barColor}`} style={{ width: `${Math.min(usage.percentage_used, 100)}%` }} />
                    </div>
                </div>

                <div className="flex flex-wrap gap-3">
                    <div className="relative max-w-xs flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input value={search} onChange={e => setSearch(e.target.value)} onKeyDown={e => e.key === 'Enter' && apply()} placeholder="Buscar por título…" className="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                    </div>
                    <select value={filters.category ?? ''} onChange={e => apply({ category: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">Todas as categorias</option>
                        {Object.entries(categories).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
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
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Documento</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Categoria</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Visibilidade</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Condomínio</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Validade</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tamanho</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {documents.data.length === 0 && (
                                <tr><td colSpan={7} className="px-4 py-8 text-center text-sm text-gray-500">Nenhum documento encontrado.</td></tr>
                            )}
                            {documents.data.map(d => (
                                <tr key={d.id} className="transition-colors hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <p className="font-medium text-gray-900">{d.title}</p>
                                        {d.storage_object?.original_filename && <p className="text-xs text-gray-400">{d.storage_object.original_filename}</p>}
                                    </td>
                                    <td className="px-4 py-3 text-gray-600">{categories[d.category] ?? d.category}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${visibilityStyle[d.visibility] ?? ''}`}>{visibilities[d.visibility] ?? d.visibility}</span>
                                    </td>
                                    <td className="px-4 py-3 text-xs text-gray-600">{d.condominium?.name ?? '—'}</td>
                                    <td className="px-4 py-3"><ValidityBadge d={d} /></td>
                                    <td className="px-4 py-3 text-xs text-gray-500">{formatBytes(d.storage_object?.file_size_bytes ?? 0)}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            {can('documents:download') && d.storage_object && (
                                                <a href={route('documents.download', d.id)} className="rounded p-1.5 text-gray-400 transition-colors hover:bg-blue-50 hover:text-blue-600"><Download className="h-4 w-4" /></a>
                                            )}
                                            {can('documents:upload') && (
                                                <Link href={route('documents.edit', d.id)} className="rounded p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"><Pencil className="h-4 w-4" /></Link>
                                            )}
                                            {can('documents:delete') && (
                                                <button onClick={() => destroy(d.id, d.title)} className="rounded p-1.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-500"><Trash2 className="h-4 w-4" /></button>
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
