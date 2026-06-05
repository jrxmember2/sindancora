import { useEffect } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Send, ArrowLeft, Play, Ban, Trash2, Paperclip } from 'lucide-react';

interface Campaign {
    id: string;
    name: string;
    body: string;
    status: string;
    status_label: string;
    condominium: string | null;
    connection: string | null;
    connection_connected: boolean;
    has_media: boolean;
    throttle_seconds: number;
    total_recipients: number;
    sent_count: number;
    failed_count: number;
    skipped_count: number;
    scheduled_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    is_editable: boolean;
}
interface Recipient { id: string; name: string | null; phone: string; status: string; error: string | null; sent_at: string | null }
interface Props { campaign: Campaign; recipients: Recipient[] }

const recipientStyle: Record<string, string> = {
    pending: 'bg-gray-100 text-gray-500',
    sent: 'bg-green-100 text-green-700',
    failed: 'bg-red-100 text-red-600',
    skipped: 'bg-amber-100 text-amber-700',
};
const recipientLabel: Record<string, string> = { pending: 'Pendente', sent: 'Enviada', failed: 'Falhou', skipped: 'Pulada' };

export default function CampaignsShow({ campaign, recipients }: Props) {
    // Atualiza o progresso enquanto envia.
    useEffect(() => {
        if (campaign.status !== 'sending') return;
        const t = setInterval(() => router.reload({ only: ['campaign', 'recipients'] }), 4000);
        return () => clearInterval(t);
    }, [campaign.status]);

    const done = campaign.sent_count + campaign.failed_count + campaign.skipped_count;
    const pct = campaign.total_recipients > 0 ? Math.round((done / campaign.total_recipients) * 100) : 0;

    const start = () => {
        if (confirm(`Iniciar o disparo para ${campaign.total_recipients} destinatário(s)?`)) {
            router.post(route('campaigns.start', campaign.id), {}, { preserveScroll: true });
        }
    };
    const cancel = () => confirm('Cancelar esta campanha?') && router.post(route('campaigns.cancel', campaign.id), {}, { preserveScroll: true });
    const remove = () => confirm('Remover esta campanha?') && router.delete(route('campaigns.destroy', campaign.id));

    return (
        <AppLayout>
            <Head title={`Campanha — ${campaign.name}`} />

            <div className="mx-auto max-w-4xl space-y-6">
                <Link href={route('campaigns.index')} className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                    <ArrowLeft className="h-4 w-4" /> Disparos
                </Link>

                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900"><Send className="h-6 w-6 text-blue-600" /> {campaign.name}</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            {campaign.condominium ?? '—'}{campaign.connection ? ` · ${campaign.connection}` : ''} · {campaign.status_label}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {campaign.is_editable && (
                            <button onClick={start} disabled={!campaign.connection_connected} title={!campaign.connection_connected ? 'Conexão desconectada' : undefined} className="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">
                                <Play className="h-4 w-4" /> {campaign.scheduled_at ? 'Enviar agora' : 'Iniciar disparo'}
                            </button>
                        )}
                        {(campaign.status === 'sending' || campaign.status === 'scheduled') && (
                            <button onClick={cancel} className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <Ban className="h-4 w-4" /> Cancelar
                            </button>
                        )}
                        <button onClick={remove} className="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600"><Trash2 className="h-4 w-4" /></button>
                    </div>
                </div>

                {/* Progresso */}
                <div className="rounded-xl border border-gray-200 bg-white p-4">
                    <div className="mb-1 flex justify-between text-sm text-gray-600">
                        <span>{done} de {campaign.total_recipients} processado(s)</span><span>{pct}%</span>
                    </div>
                    <div className="h-2.5 w-full rounded-full bg-gray-100">
                        <div className="h-2.5 rounded-full bg-green-500" style={{ width: `${pct}%` }} />
                    </div>
                    <div className="mt-3 grid grid-cols-3 gap-2 text-center text-sm">
                        <div><p className="text-lg font-bold text-green-600">{campaign.sent_count}</p><p className="text-xs text-gray-500">enviadas</p></div>
                        <div><p className="text-lg font-bold text-red-500">{campaign.failed_count}</p><p className="text-xs text-gray-500">falhas</p></div>
                        <div><p className="text-lg font-bold text-amber-500">{campaign.skipped_count}</p><p className="text-xs text-gray-500">puladas (opt-out)</p></div>
                    </div>
                </div>

                {/* Mensagem */}
                <div className="rounded-xl border border-gray-200 bg-white p-4">
                    <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Mensagem</p>
                    <p className="whitespace-pre-wrap text-sm text-gray-800">{campaign.body}</p>
                    {campaign.has_media && <p className="mt-2 flex items-center gap-1 text-xs text-gray-500"><Paperclip className="h-3.5 w-3.5" /> Com anexo</p>}
                    <p className="mt-2 text-xs text-gray-400">Intervalo entre envios: {campaign.throttle_seconds}s</p>
                </div>

                {/* Destinatários */}
                <div className="rounded-xl border border-gray-200 bg-white">
                    <p className="border-b border-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-700">Destinatários ({recipients.length})</p>
                    <div className="max-h-96 overflow-y-auto">
                        {recipients.length === 0 ? (
                            <p className="px-4 py-6 text-center text-sm text-gray-400">Nenhum destinatário.</p>
                        ) : (
                            <table className="w-full text-sm">
                                <tbody>
                                    {recipients.map((r) => (
                                        <tr key={r.id} className="border-b border-gray-50">
                                            <td className="px-4 py-2 text-gray-800">{r.name ?? '—'}</td>
                                            <td className="px-4 py-2 text-gray-500">{r.phone}</td>
                                            <td className="px-4 py-2">
                                                <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${recipientStyle[r.status] ?? ''}`}>{recipientLabel[r.status] ?? r.status}</span>
                                                {r.error && <span className="ml-2 text-xs text-red-500">{r.error}</span>}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
