import PortalLayout from '@/Layouts/PortalLayout';
import { Head, router } from '@inertiajs/react';
import { FileText, Download, Search } from 'lucide-react';
import { useState } from 'react';

interface Document {
    id: string; title: string; description: string | null; category: string; created_at: string;
    condominium: { name: string } | null;
    storage_object: { file_size_bytes: number; mime_type: string } | null;
}
interface Props {
    documents: { data: Document[] };
    categories: Record<string, string>;
    filters: { search?: string; category?: string };
}

function fileSize(bytes: number | undefined): string {
    if (!bytes) return '';
    const mb = bytes / (1024 * 1024);
    if (mb >= 1) return `${mb.toFixed(1)} MB`;
    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

export default function PortalDocuments({ documents, categories, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const apply = (params: Record<string, string>) => {
        router.get(route('portal.documents.index'), { ...filters, ...params }, { preserveState: true, replace: true });
    };

    return (
        <PortalLayout title="Documentos">
            <Head title="Documentos" />

            <div className="mb-4 flex flex-col gap-2 sm:flex-row">
                <form
                    onSubmit={(e) => { e.preventDefault(); apply({ search }); }}
                    className="relative flex-1"
                >
                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Buscar documento…" className="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                </form>
                <select value={filters.category ?? ''} onChange={(e) => apply({ category: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="">Todas as categorias</option>
                    {Object.entries(categories).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
            </div>

            <div className="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                {documents.data.length === 0 && (
                    <div className="px-4 py-10 text-center">
                        <FileText className="mx-auto h-8 w-8 text-gray-300" />
                        <p className="mt-2 text-sm text-gray-400">Nenhum documento disponível.</p>
                    </div>
                )}
                {documents.data.map((d) => (
                    <a key={d.id} href={route('portal.documents.download', d.id)} className="flex items-center gap-3 px-4 py-3.5 transition-colors hover:bg-gray-50">
                        <span className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600"><FileText className="h-5 w-5" /></span>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium text-gray-900">{d.title}</p>
                            <p className="text-xs text-gray-500">
                                {categories[d.category] ?? d.category}
                                {d.condominium?.name ? ` · ${d.condominium.name}` : ''}
                                {d.storage_object?.file_size_bytes ? ` · ${fileSize(d.storage_object.file_size_bytes)}` : ''}
                            </p>
                        </div>
                        <Download className="h-4 w-4 flex-shrink-0 text-gray-400" />
                    </a>
                ))}
            </div>
        </PortalLayout>
    );
}
