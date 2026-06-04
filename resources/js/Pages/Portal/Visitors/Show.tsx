import { Link, router } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';
import PortalLayout from '@/Layouts/PortalLayout';
import { ArrowLeft, Trash2 } from 'lucide-react';

interface Authorization {
    id: string;
    visitor_name: string;
    visitor_document: string | null;
    visitor_phone: string | null;
    type_label: string;
    status: string;
    status_label: string;
    token: string;
    condominium: string | null;
    unit: string | null;
    valid_from: string | null;
    valid_until: string | null;
    notes: string | null;
}

const statusStyles: Record<string, string> = {
    active: 'bg-green-100 text-green-700',
    used: 'bg-gray-100 text-gray-600',
    expired: 'bg-amber-100 text-amber-700',
    revoked: 'bg-red-100 text-red-700',
};

export default function VisitorsShow({ authorization: a }: { authorization: Authorization }) {
    const revoke = () => {
        if (confirm('Revogar esta autorização? O visitante não poderá mais entrar com este código.')) {
            router.post(route('portal.visitors.revoke', a.id));
        }
    };

    return (
        <PortalLayout title="Autorização de visitante">
            <Link href={route('portal.visitors.index')} className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <ArrowLeft className="h-4 w-4" /> Voltar
            </Link>

            <div className="overflow-hidden rounded-xl border border-gray-200 bg-white">
                {/* QR Code */}
                <div className="flex flex-col items-center gap-3 border-b border-gray-100 bg-gray-50 px-6 py-8">
                    {a.status === 'active' ? (
                        <>
                            <div className="rounded-xl bg-white p-4 shadow-sm">
                                <QRCodeSVG value={a.token} size={180} level="M" />
                            </div>
                            <p className="text-sm text-gray-500">Mostre este QR Code na portaria</p>
                            <p className="font-mono text-2xl font-bold tracking-widest text-gray-900">{a.token}</p>
                        </>
                    ) : (
                        <div className="text-center">
                            <span className={`inline-block rounded-full px-3 py-1 text-sm font-medium ${statusStyles[a.status] ?? 'bg-gray-100 text-gray-600'}`}>{a.status_label}</span>
                            <p className="mt-2 text-sm text-gray-500">Esta autorização não está mais ativa.</p>
                        </div>
                    )}
                </div>

                {/* Detalhes */}
                <dl className="grid grid-cols-2 gap-4 p-6 text-sm">
                    <div className="col-span-2">
                        <dt className="text-gray-500">Visitante</dt>
                        <dd className="font-medium text-gray-900">{a.visitor_name}</dd>
                    </div>
                    {a.visitor_document && (
                        <div>
                            <dt className="text-gray-500">Documento</dt>
                            <dd className="font-medium text-gray-900">{a.visitor_document}</dd>
                        </div>
                    )}
                    {a.visitor_phone && (
                        <div>
                            <dt className="text-gray-500">Telefone</dt>
                            <dd className="font-medium text-gray-900">{a.visitor_phone}</dd>
                        </div>
                    )}
                    <div>
                        <dt className="text-gray-500">Tipo</dt>
                        <dd className="font-medium text-gray-900">{a.type_label}</dd>
                    </div>
                    <div>
                        <dt className="text-gray-500">Unidade</dt>
                        <dd className="font-medium text-gray-900">{a.unit ?? '—'} · {a.condominium}</dd>
                    </div>
                    {(a.valid_from || a.valid_until) && (
                        <div className="col-span-2">
                            <dt className="text-gray-500">Validade</dt>
                            <dd className="font-medium text-gray-900">{a.valid_from ?? '…'} até {a.valid_until ?? 'sem limite'}</dd>
                        </div>
                    )}
                    {a.notes && (
                        <div className="col-span-2">
                            <dt className="text-gray-500">Observações</dt>
                            <dd className="text-gray-700">{a.notes}</dd>
                        </div>
                    )}
                </dl>

                {a.status === 'active' && (
                    <div className="border-t border-gray-100 px-6 py-4">
                        <button onClick={revoke} className="inline-flex items-center gap-1.5 text-sm font-medium text-red-600 hover:text-red-700">
                            <Trash2 className="h-4 w-4" /> Revogar autorização
                        </button>
                    </div>
                )}
            </div>
        </PortalLayout>
    );
}
