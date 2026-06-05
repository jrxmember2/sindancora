import { useEffect, useRef, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';
import { MessagesSquare, Send, UserCheck, CheckCircle2, RotateCcw, Paperclip, FileText, Zap, Download, Plus, X } from 'lucide-react';
import { maskPhone } from '@/lib/masks';

interface Conversation {
    id: string;
    contact_name: string | null;
    contact_phone: string;
    status: string;
    unread_count: number;
    connection: string | null;
    condominium: string | null;
    sector: string | null;
    assignee: string | null;
    last_message_at: string | null;
}
interface Media { type: string; name: string | null; mime: string | null; is_image: boolean; url: string | null }
interface Message { id: string; direction: string; body: string | null; is_bot?: boolean; media: Media | null; created_at: string | null }
interface QuickReply { id: string; title: string; body: string; sector_id: string | null }
interface Selected {
    id: string;
    contact_name: string | null;
    contact_phone: string;
    status: string;
    connection: string | null;
    connection_status: string | null;
    condominium: string | null;
    sector: string | null;
    assignee: string | null;
    assigned_to_me: boolean;
    messages: Message[];
}
interface Option { value: string; label: string }
interface Props {
    conversations: Conversation[];
    selected: Selected | null;
    condominiums: Option[];
    sectors: Option[];
    connections: Option[];
    quickReplies: QuickReply[];
    filters: { condominium_id?: string; sector_id?: string; status?: string; conversation?: string };
}

const time = (iso: string | null) => (iso ? new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '');
const displayName = (c: { contact_name: string | null; contact_phone: string }) => c.contact_name || c.contact_phone;

export default function InboxIndex({ conversations, selected, condominiums, sectors, connections, quickReplies, filters }: Props) {
    const navigate = (params: Record<string, string | undefined>) =>
        router.get(route('inbox.index'), { ...filters, ...params }, { preserveState: true, preserveScroll: true, replace: true });

    const openConversation = (id: string) => navigate({ conversation: id });

    const fileRef = useRef<HTMLInputElement>(null);
    const [qrOpen, setQrOpen] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [newOpen, setNewOpen] = useState(false);
    const tenantId = usePage<PageProps>().props.tenant?.id;

    // Tempo real (Reverb): atualiza ao receber/enviar mensagem. Mantém um poll lento de fallback.
    useEffect(() => {
        const reload = () => router.reload({ only: ['conversations', 'selected'] });

        const channel = window.Echo && tenantId ? `tenant.${tenantId}.inbox` : null;
        if (channel) {
            window.Echo!.private(channel).listen('.conversation.updated', reload);
        }

        const poll = setInterval(reload, channel ? 30000 : 5000);

        return () => {
            clearInterval(poll);
            if (channel) window.Echo!.leave(channel);
        };
    }, [tenantId]);

    const reply = useForm({ body: '' });
    const send = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selected) return;
        reply.post(route('inbox.send', selected.id), { preserveScroll: true, onSuccess: () => reply.reset('body') });
    };

    const onPickFile = (e: React.ChangeEvent<HTMLInputElement>) => {
        const f = e.target.files?.[0];
        if (!f || !selected) return;
        const fd = new FormData();
        fd.append('file', f);
        if (reply.data.body.trim()) fd.append('caption', reply.data.body);
        setUploading(true);
        router.post(route('inbox.sendMedia', selected.id), fd, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => reply.reset('body'),
            onFinish: () => { setUploading(false); if (fileRef.current) fileRef.current.value = ''; },
        });
    };

    const useQuickReply = (body: string) => {
        reply.setData('body', reply.data.body ? `${reply.data.body} ${body}` : body);
        setQrOpen(false);
    };

    const assign = () => selected && router.post(route('inbox.assign', selected.id), {}, { preserveScroll: true });
    const toggleStatus = () => selected && router.post(route('inbox.status', selected.id), {}, { preserveScroll: true });

    return (
        <AppLayout>
            <Head title="Atendimento WhatsApp" />

            <div className="mb-4 flex items-center gap-2">
                <MessagesSquare className="h-6 w-6 text-green-600" />
                <h1 className="text-2xl font-bold text-gray-900">Atendimento WhatsApp</h1>
                <button
                    onClick={() => setNewOpen(true)}
                    disabled={connections.length === 0}
                    title={connections.length === 0 ? 'Nenhuma conexão conectada' : undefined}
                    className="ml-auto inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
                >
                    <Plus className="h-4 w-4" /> Nova conversa
                </button>
            </div>

            <div className="flex h-[calc(100vh-180px)] overflow-hidden rounded-xl border border-gray-200 bg-white">
                {/* Lista de conversas */}
                <div className="flex w-80 flex-shrink-0 flex-col border-r border-gray-100">
                    <div className="space-y-2 border-b border-gray-100 p-3">
                        <select value={filters.condominium_id ?? ''} onChange={(e) => navigate({ condominium_id: e.target.value || undefined })} className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Todos os condomínios</option>
                            {condominiums.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                        </select>
                        {sectors.length > 0 && (
                            <select value={filters.sector_id ?? ''} onChange={(e) => navigate({ sector_id: e.target.value || undefined })} className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Todos os setores</option>
                                {sectors.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                            </select>
                        )}
                        <div className="flex rounded-lg border border-gray-200 p-0.5 text-sm">
                            {(['open', 'closed'] as const).map((s) => (
                                <button key={s} onClick={() => navigate({ status: s })} className={`flex-1 rounded-md px-2 py-1 font-medium ${(filters.status ?? 'open') === s ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'}`}>
                                    {s === 'open' ? 'Abertas' : 'Encerradas'}
                                </button>
                            ))}
                        </div>
                    </div>
                    <div className="flex-1 overflow-y-auto">
                        {conversations.length === 0 && <p className="px-4 py-8 text-center text-sm text-gray-400">Nenhuma conversa.</p>}
                        {conversations.map((c) => (
                            <button
                                key={c.id}
                                onClick={() => openConversation(c.id)}
                                className={`flex w-full flex-col items-start gap-0.5 border-b border-gray-50 px-3 py-2.5 text-left hover:bg-gray-50 ${selected?.id === c.id ? 'bg-blue-50/60' : ''}`}
                            >
                                <div className="flex w-full items-center gap-2">
                                    <span className="min-w-0 flex-1 truncate text-sm font-medium text-gray-900">{displayName(c)}</span>
                                    {c.unread_count > 0 && <span className="flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-green-500 px-1 text-[10px] font-semibold text-white">{c.unread_count}</span>}
                                    <span className="flex-shrink-0 text-[10px] text-gray-400">{time(c.last_message_at)}</span>
                                </div>
                                <span className="truncate text-xs text-gray-500">
                                    {c.condominium ?? 'Sem condomínio'}{c.sector ? ` · ${c.sector}` : ''}{c.assignee ? ` · ${c.assignee}` : ''}
                                </span>
                            </button>
                        ))}
                    </div>
                </div>

                {/* Thread */}
                <div className="flex min-w-0 flex-1 flex-col">
                    {!selected ? (
                        <div className="flex flex-1 items-center justify-center text-sm text-gray-400">Selecione uma conversa.</div>
                    ) : (
                        <>
                            <div className="flex items-center gap-3 border-b border-gray-100 px-4 py-3">
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-semibold text-gray-900">{displayName(selected)}</p>
                                    <p className="truncate text-xs text-gray-500">
                                        {selected.contact_phone}
                                        {selected.condominium ? ` · ${selected.condominium}` : ' · Sem condomínio'}
                                        {selected.sector ? ` · ${selected.sector}` : ''}
                                        {selected.assignee ? ` · Resp.: ${selected.assignee}` : ''}
                                    </p>
                                </div>
                                <button onClick={assign} className={`inline-flex items-center gap-1 rounded-lg border px-2.5 py-1.5 text-xs font-medium ${selected.assigned_to_me ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-600 hover:bg-gray-50'}`}>
                                    <UserCheck className="h-3.5 w-3.5" /> {selected.assigned_to_me ? 'Atribuída a mim' : 'Atender'}
                                </button>
                                <button onClick={toggleStatus} className="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                                    {selected.status === 'open' ? <><CheckCircle2 className="h-3.5 w-3.5" /> Encerrar</> : <><RotateCcw className="h-3.5 w-3.5" /> Reabrir</>}
                                </button>
                            </div>

                            <div className="flex flex-1 flex-col gap-2 overflow-y-auto bg-gray-50 p-4">
                                {selected.messages.length === 0 && <p className="m-auto text-sm text-gray-400">Sem mensagens.</p>}
                                {selected.messages.map((m) => {
                                    const out = m.direction === 'out';
                                    const bubble = m.is_bot ? 'self-end rounded-br-sm bg-emerald-600 text-white' : out ? 'self-end rounded-br-sm bg-blue-600 text-white' : 'self-start rounded-bl-sm bg-white text-gray-800 shadow-sm';
                                    return (
                                        <div key={m.id} className={`max-w-[75%] rounded-2xl px-3 py-2 text-sm ${bubble}`}>
                                            {m.is_bot && <p className="mb-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-100">🤖 Chatbot</p>}
                                            {m.media && <MediaBubble media={m.media} out={out} />}
                                            {m.body && <p className="whitespace-pre-wrap break-words">{m.body}</p>}
                                            <p className={`mt-0.5 text-[10px] ${out ? 'text-blue-100' : 'text-gray-400'}`}>{time(m.created_at)}</p>
                                        </div>
                                    );
                                })}
                            </div>

                            {selected.connection_status !== 'connected' && (
                                <p className="bg-amber-50 px-4 py-1.5 text-center text-xs text-amber-700">Número desconectado — as mensagens podem não ser entregues.</p>
                            )}

                            <form onSubmit={send} className="flex items-center gap-2 border-t border-gray-100 p-3">
                                <input ref={fileRef} type="file" className="hidden" onChange={onPickFile} />
                                <button type="button" onClick={() => fileRef.current?.click()} disabled={uploading} title="Anexar arquivo" className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 disabled:opacity-50">
                                    <Paperclip className="h-4 w-4" />
                                </button>

                                {quickReplies.length > 0 && (
                                    <div className="relative flex-shrink-0">
                                        <button type="button" onClick={() => setQrOpen((o) => !o)} title="Respostas rápidas" className="flex h-10 w-10 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100">
                                            <Zap className="h-4 w-4" />
                                        </button>
                                        {qrOpen && (
                                            <>
                                                <div className="fixed inset-0 z-40" onClick={() => setQrOpen(false)} />
                                                <div className="absolute bottom-12 left-0 z-50 max-h-72 w-72 overflow-y-auto rounded-xl border border-gray-100 bg-white shadow-lg">
                                                    {quickReplies.map((q) => (
                                                        <button key={q.id} type="button" onClick={() => useQuickReply(q.body)} className="block w-full border-b border-gray-50 px-3 py-2 text-left hover:bg-gray-50">
                                                            <span className="block text-sm font-medium text-gray-900">{q.title}</span>
                                                            <span className="block truncate text-xs text-gray-500">{q.body}</span>
                                                        </button>
                                                    ))}
                                                </div>
                                            </>
                                        )}
                                    </div>
                                )}

                                <input
                                    type="text"
                                    value={reply.data.body}
                                    onChange={(e) => reply.setData('body', e.target.value)}
                                    placeholder={uploading ? 'Enviando arquivo…' : 'Digite uma mensagem…'}
                                    className="flex-1 rounded-full border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                                />
                                <button type="submit" disabled={reply.processing || uploading || !reply.data.body.trim()} className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50">
                                    <Send className="h-4 w-4" />
                                </button>
                            </form>
                        </>
                    )}
                </div>
            </div>

            {newOpen && <NewConversationModal connections={connections} onClose={() => setNewOpen(false)} />}
        </AppLayout>
    );
}

function NewConversationModal({ connections, onClose }: { connections: Option[]; onClose: () => void }) {
    const form = useForm({ connection_id: connections[0]?.value ?? '', phone: '', body: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('inbox.start'), { onSuccess: onClose });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
            <div className="w-full max-w-md rounded-xl bg-white p-6" onClick={(e) => e.stopPropagation()}>
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="font-semibold text-gray-900">Nova conversa</h2>
                    <button onClick={onClose} className="rounded p-1 text-gray-400 hover:bg-gray-100"><X className="h-4 w-4" /></button>
                </div>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Conexão</label>
                        <select value={form.data.connection_id} onChange={(e) => form.setData('connection_id', e.target.value)} className="w-full rounded-lg border-gray-300 text-sm focus:border-green-500 focus:ring-green-500">
                            {connections.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Telefone</label>
                        <input type="tel" value={form.data.phone} onChange={(e) => form.setData('phone', maskPhone(e.target.value))} placeholder="(00) 00000-0000" className="w-full rounded-lg border-gray-300 text-sm focus:border-green-500 focus:ring-green-500" />
                        {form.errors.phone && <p className="mt-1 text-xs text-red-600">{form.errors.phone}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Mensagem</label>
                        <textarea value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} rows={3} placeholder="Digite a primeira mensagem…" className="w-full rounded-lg border-gray-300 text-sm focus:border-green-500 focus:ring-green-500" />
                        {form.errors.body && <p className="mt-1 text-xs text-red-600">{form.errors.body}</p>}
                    </div>
                    <div className="flex justify-end gap-2">
                        <button type="button" onClick={onClose} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                        <button type="submit" disabled={form.processing || !form.data.connection_id || !form.data.phone.trim() || !form.data.body.trim()} className="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">Enviar</button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function MediaBubble({ media, out }: { media: Media; out: boolean }) {
    if (!media.url) {
        return <p className={`flex items-center gap-1.5 text-xs italic ${out ? 'text-blue-100' : 'text-gray-400'}`}><FileText className="h-3.5 w-3.5" /> Mídia indisponível</p>;
    }

    if (media.is_image) {
        return (
            <a href={media.url} target="_blank" rel="noopener noreferrer" className="mb-1 block">
                <img src={media.url} alt={media.name ?? 'imagem'} className="max-h-60 rounded-lg" />
            </a>
        );
    }

    if (media.type === 'video') {
        return <video src={media.url} controls className="mb-1 max-h-60 rounded-lg" />;
    }

    if (media.type === 'audio') {
        return <audio src={media.url} controls className="mb-1 w-56" />;
    }

    return (
        <a href={media.url} target="_blank" rel="noopener noreferrer" className={`mb-1 flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm ${out ? 'bg-blue-500/40' : 'bg-gray-100'}`}>
            <FileText className="h-4 w-4 flex-shrink-0" />
            <span className="min-w-0 flex-1 truncate">{media.name ?? 'arquivo'}</span>
            <Download className="h-3.5 w-3.5 flex-shrink-0" />
        </a>
    );
}
