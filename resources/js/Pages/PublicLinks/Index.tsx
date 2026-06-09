import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';
import { Copy, Check, Link2, QrCode, RefreshCw, Inbox } from 'lucide-react';
import { useState } from 'react';

interface LinkRow {
    condominium_id: string;
    condominium_name: string;
    has_link: boolean;
    token: string | null;
    url: string | null;
    active: boolean;
    allow_resident_signup: boolean;
    allow_occurrence: boolean;
    pending: number;
}

interface Props {
    links: LinkRow[];
    canManage: boolean;
    pendingTotal: number;
}

function Toggle({ checked, disabled, onChange, label }: { checked: boolean; disabled?: boolean; onChange: (v: boolean) => void; label: string }) {
    return (
        <label className={`flex items-center gap-2 text-sm ${disabled ? 'opacity-60' : 'cursor-pointer'}`}>
            <input
                type="checkbox"
                checked={checked}
                disabled={disabled}
                onChange={(e) => onChange(e.target.checked)}
                className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
            <span className="text-gray-700">{label}</span>
        </label>
    );
}

function LinkCard({ row, canManage }: { row: LinkRow; canManage: boolean }) {
    const [showQr, setShowQr] = useState(false);
    const [copied, setCopied] = useState(false);

    const update = (patch: Partial<Pick<LinkRow, 'active' | 'allow_resident_signup' | 'allow_occurrence'>>) => {
        router.put(route('public-links.update', row.condominium_id), {
            active: patch.active ?? row.active,
            allow_resident_signup: patch.allow_resident_signup ?? row.allow_resident_signup,
            allow_occurrence: patch.allow_occurrence ?? row.allow_occurrence,
        }, { preserveScroll: true });
    };

    const generate = () => {
        const msg = row.has_link
            ? 'Gerar um novo link? O link e o QR atuais deixarão de funcionar.'
            : 'Gerar link público para este condomínio?';
        if (confirm(msg)) {
            router.post(route('public-links.generate', row.condominium_id), {}, { preserveScroll: true });
        }
    };

    const copy = () => {
        if (!row.url) return;
        navigator.clipboard.writeText(row.url);
        setCopied(true);
        setTimeout(() => setCopied(false), 1500);
    };

    return (
        <div className="rounded-xl border border-gray-200 bg-white p-5">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h3 className="font-semibold text-gray-900">{row.condominium_name}</h3>
                    {row.has_link ? (
                        <span className={`mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-medium ${row.active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                            {row.active ? 'Ativo' : 'Inativo'}
                        </span>
                    ) : (
                        <span className="mt-1 inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">Sem link</span>
                    )}
                </div>
                {row.pending > 0 && (
                    <Link
                        href={route('public-links.moderation.index', { condominium_id: row.condominium_id, status: 'pending' })}
                        className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700 hover:bg-amber-200"
                    >
                        <Inbox className="h-3.5 w-3.5" /> {row.pending} a moderar
                    </Link>
                )}
            </div>

            {row.has_link && row.url && (
                <div className="mt-4 space-y-3">
                    <div className="flex items-center gap-2">
                        <Link2 className="h-4 w-4 shrink-0 text-gray-400" />
                        <input readOnly value={row.url} className="min-w-0 flex-1 truncate rounded-lg border-gray-200 bg-gray-50 px-3 py-1.5 text-xs text-gray-600" />
                        <button onClick={copy} className="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                            {copied ? <Check className="h-3.5 w-3.5 text-green-600" /> : <Copy className="h-3.5 w-3.5" />}
                            {copied ? 'Copiado' : 'Copiar'}
                        </button>
                        <button onClick={() => setShowQr((v) => !v)} className="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                            <QrCode className="h-3.5 w-3.5" /> QR
                        </button>
                    </div>

                    {showQr && (
                        <div className="flex flex-col items-center gap-2 rounded-lg bg-gray-50 py-5">
                            <div className="rounded-xl bg-white p-3 shadow-sm">
                                <QRCodeSVG value={row.url} size={168} level="M" />
                            </div>
                            <p className="text-xs text-gray-500">Imprima e fixe na portaria/elevador</p>
                        </div>
                    )}

                    {canManage && (
                        <div className="flex flex-wrap items-center gap-4 border-t border-gray-100 pt-3">
                            <Toggle label="Link ativo" checked={row.active} onChange={(v) => update({ active: v })} />
                            <Toggle label="Auto-cadastro" checked={row.allow_resident_signup} onChange={(v) => update({ allow_resident_signup: v })} />
                            <Toggle label="Ocorrências" checked={row.allow_occurrence} onChange={(v) => update({ allow_occurrence: v })} />
                        </div>
                    )}
                </div>
            )}

            {canManage && (
                <div className="mt-4">
                    <button onClick={generate} className="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 hover:text-blue-700">
                        <RefreshCw className="h-4 w-4" /> {row.has_link ? 'Gerar novo link' : 'Gerar link público'}
                    </button>
                </div>
            )}
        </div>
    );
}

export default function PublicLinksIndex({ links, canManage, pendingTotal }: Props) {
    return (
        <AppLayout>
            <Head title="Links públicos" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Links públicos</h1>
                    <p className="mt-1 text-sm text-gray-500">Links e QR Code por condomínio para auto-cadastro de morador e abertura de ocorrência, com moderação.</p>
                </div>
                <Link
                    href={route('public-links.moderation.index')}
                    className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                >
                    <Inbox className="h-4 w-4" /> Moderação
                    {pendingTotal > 0 && <span className="rounded-full bg-white/25 px-1.5 text-xs">{pendingTotal}</span>}
                </Link>
            </div>

            {links.length === 0 ? (
                <p className="rounded-xl border border-dashed border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500">
                    Nenhum condomínio disponível no seu escopo.
                </p>
            ) : (
                <div className="grid gap-4 md:grid-cols-2">
                    {links.map((row) => (
                        <LinkCard key={row.condominium_id} row={row} canManage={canManage} />
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
