import PortalLayout from '@/Layouts/PortalLayout';
import { Head, Link } from '@inertiajs/react';
import { AlertCircle, Plus, ChevronRight } from 'lucide-react';

interface Occurrence {
    id: string; title: string; category: string; priority: string; status: string; created_at: string;
    condominium: { name: string } | null; unit: { number: string } | null;
}
interface Props {
    occurrences: { data: Occurrence[] };
    categories: Record<string, string>;
    priorities: Record<string, string>;
    statuses: Record<string, string>;
}

const statusStyles: Record<string, string> = {
    open: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-amber-100 text-amber-700',
    closed: 'bg-gray-100 text-gray-600',
};

export default function PortalOccurrences({ occurrences, categories, statuses }: Props) {
    return (
        <PortalLayout title="Ocorrências">
            <Head title="Ocorrências" />

            <div className="mb-4 flex justify-end">
                <Link href={route('portal.occurrences.create')} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    <Plus className="h-4 w-4" /> Nova ocorrência
                </Link>
            </div>

            <div className="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                {occurrences.data.length === 0 && (
                    <div className="px-4 py-10 text-center">
                        <AlertCircle className="mx-auto h-8 w-8 text-gray-300" />
                        <p className="mt-2 text-sm text-gray-400">Você ainda não abriu nenhuma ocorrência.</p>
                        <Link href={route('portal.occurrences.create')} className="mt-2 inline-block text-sm text-blue-600 hover:text-blue-700">Abrir a primeira</Link>
                    </div>
                )}
                {occurrences.data.map((o) => (
                    <Link key={o.id} href={route('portal.occurrences.show', o.id)} className="flex items-center gap-3 px-4 py-3.5 transition-colors hover:bg-gray-50">
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium text-gray-900">{o.title}</p>
                            <p className="text-xs text-gray-500">
                                {categories[o.category] ?? o.category}
                                {o.unit?.number ? ` · Unid. ${o.unit.number}` : ''} · {new Date(o.created_at).toLocaleDateString('pt-BR')}
                            </p>
                        </div>
                        <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${statusStyles[o.status] ?? 'bg-gray-100 text-gray-600'}`}>{statuses[o.status] ?? o.status}</span>
                        <ChevronRight className="h-4 w-4 flex-shrink-0 text-gray-300" />
                    </Link>
                ))}
            </div>
        </PortalLayout>
    );
}
