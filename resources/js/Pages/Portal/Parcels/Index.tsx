import PortalLayout from '@/Layouts/PortalLayout';
import { Head, router } from '@inertiajs/react';
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

function formatTime(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

export default function Index({ parcels, statuses }: { parcels: ParcelRow[]; statuses: Record<string, string> }) {
    const confirm = (id: string) => router.post(route('portal.parcels.pickup', id), {}, { preserveScroll: true });

    return (
        <PortalLayout title="Encomendas">
            <Head title="Encomendas" />

            <div className="space-y-2">
                {parcels.length === 0 && (
                    <p className="rounded-xl border border-gray-100 bg-white py-10 text-center text-sm text-gray-400">
                        Nenhuma encomenda registrada para a sua unidade.
                    </p>
                )}
                {parcels.map((p) => (
                    <div key={p.id} className={`flex items-center gap-3 rounded-xl border bg-white p-4 ${p.status === 'awaiting' ? 'border-amber-200' : 'border-gray-100'}`}>
                        <span className={`flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-lg ${p.status === 'awaiting' ? 'bg-amber-50 text-amber-600' : 'bg-gray-100 text-gray-400'}`}>
                            <Package className="h-6 w-6" />
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="truncate font-medium text-gray-900">{p.description}</p>
                            <p className="truncate text-xs text-gray-500">
                                {p.carrier ? `${p.carrier} · ` : ''}Recebida em {formatTime(p.received_at)}
                                {p.status === 'picked_up' && p.picked_up_at ? ` · retirada em ${formatTime(p.picked_up_at)}` : ''}
                            </p>
                        </div>
                        {p.status === 'awaiting' ? (
                            <button onClick={() => confirm(p.id)} className="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700">
                                <Check className="h-4 w-4" /> Confirmar retirada
                            </button>
                        ) : (
                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">{statuses[p.status]}</span>
                        )}
                    </div>
                ))}
            </div>
        </PortalLayout>
    );
}
