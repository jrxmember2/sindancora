import AppLayout from '@/Layouts/AppLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { Webhook as WebhookIcon, Plus, Trash2, Send, X, Copy, Check } from 'lucide-react';
import { useState } from 'react';

interface WebhookRow {
    id: string; url: string; description: string | null; events: string[];
    secret: string; active: boolean; created_at: string | null;
}
interface EventOption { value: string; label: string }
interface DeliveryRow {
    event: string; response_status: number | null; duration_ms: number | null;
    attempts: number; delivered_at: string | null; failed_at: string | null; created_at: string;
}
interface Props { webhooks: WebhookRow[]; events: EventOption[]; deliveries: DeliveryRow[] }

const field = 'mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';
const fmt = (iso: string | null) => (iso ? new Date(iso).toLocaleString('pt-BR') : '—');

function WebhookModal({ events, webhook, onClose }: { events: EventOption[]; webhook: WebhookRow | null; onClose: () => void }) {
    const editing = !!webhook;
    const { data, setData, post, put, processing, errors } = useForm<{ url: string; description: string; events: string[]; active: boolean }>({
        url: webhook?.url ?? '',
        description: webhook?.description ?? '',
        events: webhook?.events ?? [],
        active: webhook?.active ?? true,
    });

    const toggle = (ev: string) =>
        setData('events', data.events.includes(ev) ? data.events.filter((e) => e !== ev) : [...data.events, ev]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editing) put(route('webhooks.update', webhook!.id), { onSuccess: onClose });
        else post(route('webhooks.store'), { onSuccess: onClose });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <form onSubmit={submit} className="w-full max-w-lg space-y-4 rounded-2xl bg-white p-6 shadow-xl">
                <div className="flex items-center justify-between">
                    <h2 className="text-base font-semibold text-gray-900">{editing ? 'Editar webhook' : 'Novo webhook'}</h2>
                    <button type="button" onClick={onClose}><X className="h-5 w-5 text-gray-400 hover:text-gray-600" /></button>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">URL de destino *</label>
                    <input value={data.url} onChange={(e) => setData('url', e.target.value)} className={field} placeholder="https://meusistema.com/webhooks/sindancora" />
                    {errors.url && <p className="mt-1 text-xs text-red-600">{errors.url}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Descrição</label>
                    <input value={data.description} onChange={(e) => setData('description', e.target.value)} className={field} />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Eventos *</label>
                    <div className="mt-2 grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                        {events.map((ev) => (
                            <label key={ev.value} className="flex items-start gap-2 rounded-lg border border-gray-100 px-3 py-2 text-sm hover:bg-gray-50">
                                <input type="checkbox" checked={data.events.includes(ev.value)} onChange={() => toggle(ev.value)} className="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                <span>
                                    <span className="block font-mono text-xs text-gray-900">{ev.value}</span>
                                    <span className="block text-xs text-gray-500">{ev.label}</span>
                                </span>
                            </label>
                        ))}
                    </div>
                    {errors.events && <p className="mt-1 text-xs text-red-600">{errors.events}</p>}
                </div>
                <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" checked={data.active} onChange={(e) => setData('active', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                    Ativo
                </label>
                <div className="flex justify-end gap-2 pt-1">
                    <button type="button" onClick={onClose} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button type="submit" disabled={processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        {processing ? 'Salvando…' : 'Salvar'}
                    </button>
                </div>
            </form>
        </div>
    );
}

export default function Webhooks({ webhooks, events, deliveries }: Props) {
    const [modal, setModal] = useState<{ open: boolean; webhook: WebhookRow | null }>({ open: false, webhook: null });
    const [copiedId, setCopiedId] = useState<string | null>(null);

    const copySecret = (w: WebhookRow) => {
        navigator.clipboard.writeText(w.secret);
        setCopiedId(w.id);
        setTimeout(() => setCopiedId(null), 1500);
    };
    const test = (id: string) => router.post(route('webhooks.test', id), {}, { preserveScroll: true });
    const remove = (id: string) => confirm('Remover este webhook?') && router.delete(route('webhooks.destroy', id), { preserveScroll: true });

    return (
        <AppLayout>
            <Head title="Webhooks" />
            {modal.open && <WebhookModal events={events} webhook={modal.webhook} onClose={() => setModal({ open: false, webhook: null })} />}

            <div className="mx-auto max-w-4xl">
                <header className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-lg font-bold text-gray-900"><WebhookIcon className="h-5 w-5" /> Webhooks de saída</h1>
                        <p className="text-sm text-gray-500">Receba eventos do condomínio em tempo real. Cada envio é assinado em <code className="text-xs">X-SindAncora-Signature</code> (HMAC-SHA256 do corpo).</p>
                    </div>
                    <button onClick={() => setModal({ open: true, webhook: null })} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        <Plus className="h-4 w-4" /> Novo webhook
                    </button>
                </header>

                <div className="space-y-3">
                    {webhooks.length === 0 && <p className="rounded-xl border border-gray-100 bg-white px-4 py-8 text-center text-sm text-gray-400 shadow-sm">Nenhum webhook configurado.</p>}
                    {webhooks.map((w) => (
                        <div key={w.id} className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="flex items-center gap-2 font-medium text-gray-900">
                                        <span className="truncate font-mono text-sm">{w.url}</span>
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${w.active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>{w.active ? 'Ativo' : 'Inativo'}</span>
                                    </p>
                                    {w.description && <p className="text-xs text-gray-500">{w.description}</p>}
                                    <div className="mt-2 flex flex-wrap gap-1">
                                        {w.events.map((e) => <span key={e} className="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] text-gray-600">{e}</span>)}
                                    </div>
                                </div>
                                <div className="flex flex-shrink-0 gap-1">
                                    <button onClick={() => copySecret(w)} title="Copiar segredo" className="rounded-lg border border-gray-200 p-2 text-gray-500 hover:bg-gray-50">
                                        {copiedId === w.id ? <Check className="h-4 w-4 text-green-600" /> : <Copy className="h-4 w-4" />}
                                    </button>
                                    <button onClick={() => test(w.id)} title="Enviar teste" className="rounded-lg border border-gray-200 p-2 text-gray-500 hover:bg-gray-50"><Send className="h-4 w-4" /></button>
                                    <button onClick={() => setModal({ open: true, webhook: w })} className="rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">Editar</button>
                                    <button onClick={() => remove(w.id)} className="rounded-lg border border-red-200 p-2 text-red-600 hover:bg-red-50"><Trash2 className="h-4 w-4" /></button>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {deliveries.length > 0 && (
                    <div className="mt-6 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                        <div className="border-b border-gray-100 p-4"><h2 className="text-sm font-semibold text-gray-900">Últimas entregas</h2></div>
                        <table className="w-full text-sm">
                            <tbody className="divide-y divide-gray-50">
                                {deliveries.map((d, i) => (
                                    <tr key={i}>
                                        <td className="px-4 py-2 font-mono text-xs text-gray-700">{d.event}</td>
                                        <td className="px-4 py-2">
                                            <span className={`text-xs font-semibold ${d.delivered_at ? 'text-green-600' : d.failed_at ? 'text-red-600' : 'text-amber-600'}`}>
                                                {d.delivered_at ? `OK ${d.response_status ?? ''}` : d.failed_at ? `Falhou ${d.response_status ?? ''}` : `Tentativa ${d.attempts}`}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2 text-xs text-gray-400">{d.duration_ms ?? '—'} ms</td>
                                        <td className="px-4 py-2 text-xs text-gray-400">{fmt(d.created_at)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
