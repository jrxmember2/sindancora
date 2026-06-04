import { Link } from '@inertiajs/react';
import PortalLayout from '@/Layouts/PortalLayout';
import { Plus, QrCode, ChevronRight } from 'lucide-react';

interface Authorization {
    id: string;
    visitor_name: string;
    type_label: string;
    status: string;
    status_label: string;
    condominium: string | null;
    unit: string | null;
    valid_until: string | null;
}

const statusStyles: Record<string, string> = {
    active: 'bg-green-100 text-green-700',
    used: 'bg-gray-100 text-gray-600',
    expired: 'bg-amber-100 text-amber-700',
    revoked: 'bg-red-100 text-red-700',
};

export default function VisitorsIndex({ authorizations }: { authorizations: Authorization[] }) {
    return (
        <PortalLayout title="Visitantes">
            <div className="mb-4 flex justify-end">
                <Link href={route('portal.visitors.create')} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    <Plus className="h-4 w-4" /> Autorizar visitante
                </Link>
            </div>

            {authorizations.length === 0 ? (
                <div className="rounded-xl border border-dashed border-gray-300 bg-white py-12 text-center">
                    <QrCode className="mx-auto mb-3 h-10 w-10 text-gray-300" />
                    <p className="text-sm text-gray-500">Você ainda não autorizou nenhum visitante.</p>
                    <p className="text-sm text-gray-400">Autorize e mostre o QR Code na portaria.</p>
                </div>
            ) : (
                <ul className="space-y-2">
                    {authorizations.map((a) => (
                        <li key={a.id}>
                            <Link href={route('portal.visitors.show', a.id)} className="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 transition-colors hover:bg-gray-50">
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium text-gray-900">{a.visitor_name}</p>
                                    <p className="truncate text-sm text-gray-500">
                                        {a.type_label}{a.unit ? ` · Un. ${a.unit}` : ''}{a.valid_until ? ` · até ${a.valid_until}` : ''}
                                    </p>
                                </div>
                                <span className={`flex-shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ${statusStyles[a.status] ?? 'bg-gray-100 text-gray-600'}`}>{a.status_label}</span>
                                <ChevronRight className="h-5 w-5 flex-shrink-0 text-gray-300" />
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </PortalLayout>
    );
}
