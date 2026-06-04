import { Link } from '@inertiajs/react';
import PortariaLayout from '@/Layouts/PortariaLayout';

interface VisitRow {
    id: string;
    visitor_name: string;
    visitor_document: string | null;
    condominium: string | null;
    unit: string | null;
    check_in_at: string | null;
    check_out_at: string | null;
}
interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}
interface Paginated {
    data: VisitRow[];
    links: PageLink[];
}

function formatTime(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

export default function Log({ visits }: { visits: Paginated }) {
    return (
        <PortariaLayout title="Histórico de acessos">
            <div className="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <table className="min-w-full divide-y divide-gray-100 text-sm">
                    <thead className="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th className="px-4 py-3">Visitante</th>
                            <th className="px-4 py-3">Local</th>
                            <th className="px-4 py-3">Entrada</th>
                            <th className="px-4 py-3">Saída</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {visits.data.length === 0 && (
                            <tr>
                                <td colSpan={4} className="px-4 py-8 text-center text-gray-400">Nenhum acesso registrado.</td>
                            </tr>
                        )}
                        {visits.data.map((v) => (
                            <tr key={v.id}>
                                <td className="px-4 py-3">
                                    <span className="font-medium text-gray-900">{v.visitor_name}</span>
                                    {v.visitor_document && <span className="block text-xs text-gray-400">{v.visitor_document}</span>}
                                </td>
                                <td className="px-4 py-3 text-gray-600">{v.condominium}{v.unit ? ` · Un. ${v.unit}` : ''}</td>
                                <td className="px-4 py-3 text-gray-600">{formatTime(v.check_in_at)}</td>
                                <td className="px-4 py-3">
                                    {v.check_out_at ? (
                                        <span className="text-gray-600">{formatTime(v.check_out_at)}</span>
                                    ) : (
                                        <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Presente</span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {visits.links.length > 3 && (
                <div className="mt-4 flex flex-wrap gap-1">
                    {visits.links.map((link, i) =>
                        link.url ? (
                            <Link
                                key={i}
                                href={link.url}
                                className={`rounded-lg px-3 py-1.5 text-sm ${link.active ? 'bg-blue-600 text-white' : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50'}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : (
                            <span key={i} className="rounded-lg px-3 py-1.5 text-sm text-gray-300" dangerouslySetInnerHTML={{ __html: link.label }} />
                        ),
                    )}
                </div>
            )}
        </PortariaLayout>
    );
}
