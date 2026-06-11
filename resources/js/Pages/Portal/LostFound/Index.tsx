import PortalLayout from '@/Layouts/PortalLayout';
import { Head, Link } from '@inertiajs/react';
import { PackageSearch, Plus } from 'lucide-react';

interface Item {
    id: string;
    type: 'found' | 'lost';
    title: string;
    description: string | null;
    location: string | null;
    status: 'open' | 'resolved';
    condominium: string | null;
    occurred_on: string | null;
    photo: string | null;
}

export default function Index({ items, types, statuses }: { items: Item[]; types: Record<string, string>; statuses: Record<string, string> }) {
    return (
        <PortalLayout title="Achados & Perdidos">
            <Head title="Achados & Perdidos" />

            <Link href={route('portal.lost-found.create')} className="mb-5 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                <Plus className="h-4 w-4" /> Reportar item
            </Link>

            <div className="space-y-2">
                {items.length === 0 && <p className="rounded-xl border border-gray-100 bg-white py-10 text-center text-sm text-gray-400">Nenhum item registrado.</p>}
                {items.map((it) => (
                    <div key={it.id} className="flex gap-3 rounded-xl border border-gray-100 bg-white p-4">
                        {it.photo ? (
                            <img src={route('attachments.download', it.photo)} alt={it.title} className="h-16 w-16 flex-shrink-0 rounded-lg object-cover" />
                        ) : (
                            <span className="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-300"><PackageSearch className="h-7 w-7" /></span>
                        )}
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-2">
                                <span className={`rounded-full px-2 py-0.5 text-[11px] font-medium ${it.type === 'found' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'}`}>{types[it.type]}</span>
                                <span className={`rounded-full px-2 py-0.5 text-[11px] font-medium ${it.status === 'open' ? 'bg-gray-100 text-gray-600' : 'bg-blue-50 text-blue-700'}`}>{statuses[it.status]}</span>
                            </div>
                            <p className="mt-1 font-medium text-gray-900">{it.title}</p>
                            {it.description && <p className="text-sm text-gray-500">{it.description}</p>}
                            <p className="text-xs text-gray-400">{it.condominium}{it.location ? ` · ${it.location}` : ''}</p>
                        </div>
                    </div>
                ))}
            </div>
        </PortalLayout>
    );
}
