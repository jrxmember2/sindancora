import { useEffect } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { MessagesSquare, Send, UserCheck, CheckCircle2, RotateCcw, Building2 } from 'lucide-react';

interface Conversation {
    id: string;
    contact_name: string | null;
    contact_phone: string;
    status: string;
    unread_count: number;
    connection: string | null;
    condominium: string | null;
    assignee: string | null;
    last_message_at: string | null;
}
interface Message { id: string; direction: string; body: string | null; created_at: string | null }
interface Selected {
    id: string;
    contact_name: string | null;
    contact_phone: string;
    status: string;
    connection: string | null;
    connection_status: string | null;
    condominium: string | null;
    assignee: string | null;
    assigned_to_me: boolean;
    messages: Message[];
}
interface Option { value: string; label: string }
interface Props {
    conversations: Conversation[];
    selected: Selected | null;
    condominiums: Option[];
    filters: { condominium_id?: string; status?: string; conversation?: string };
}

const time = (iso: string | null) => (iso ? new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '');
const displayName = (c: { contact_name: string | null; contact_phone: string }) => c.contact_name || c.contact_phone;

export default function InboxIndex({ conversations, selected, condominiums, filters }: Props) {
    const navigate = (params: Record<string, string | undefined>) =>
        router.get(route('inbox.index'), { ...filters, ...params }, { preserveState: true, preserveScroll: true, replace: true });

    const openConversation = (id: string) => navigate({ conversation: id });

    // Polling (Reverb fica para a Fase 5).
    useEffect(() => {
        const t = setInterval(() => {
            router.reload({ only: ['conversations', 'selected'] });
        }, 5000);
        return () => clearInterval(t);
    }, []);

    const reply = useForm({ body: '' });
    const send = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selected) return;
        reply.post(route('inbox.send', selected.id), { preserveScroll: true, onSuccess: () => reply.reset('body') });
    };

    const assign = () => selected && router.post(route('inbox.assign', selected.id), {}, { preserveScroll: true });
    const toggleStatus = () => selected && router.post(route('inbox.status', selected.id), {}, { preserveScroll: true });

    return (
        <AppLayout>
            <Head title="Atendimento WhatsApp" />

            <div className="mb-4 flex items-center gap-2">
                <MessagesSquare className="h-6 w-6 text-green-600" />
                <h1 className="text-2xl font-bold text-gray-900">Atendimento WhatsApp</h1>
            </div>

            <div className="flex h-[calc(100vh-180px)] overflow-hidden rounded-xl border border-gray-200 bg-white">
                {/* Lista de conversas */}
                <div className="flex w-80 flex-shrink-0 flex-col border-r border-gray-100">
                    <div className="space-y-2 border-b border-gray-100 p-3">
                        <select value={filters.condominium_id ?? ''} onChange={(e) => navigate({ condominium_id: e.target.value || undefined })} className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Todos os condomínios</option>
                            {condominiums.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                        </select>
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
                                    {c.condominium ?? 'Sem condomínio'}{c.connection ? ` · ${c.connection}` : ''}{c.assignee ? ` · ${c.assignee}` : ''}
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
                                {selected.messages.map((m) => (
                                    <div key={m.id} className={`max-w-[75%] rounded-2xl px-3 py-2 text-sm ${m.direction === 'out' ? 'self-end rounded-br-sm bg-blue-600 text-white' : 'self-start rounded-bl-sm bg-white text-gray-800 shadow-sm'}`}>
                                        <p className="whitespace-pre-wrap break-words">{m.body}</p>
                                        <p className={`mt-0.5 text-[10px] ${m.direction === 'out' ? 'text-blue-100' : 'text-gray-400'}`}>{time(m.created_at)}</p>
                                    </div>
                                ))}
                            </div>

                            {selected.connection_status !== 'connected' && (
                                <p className="bg-amber-50 px-4 py-1.5 text-center text-xs text-amber-700">Número desconectado — as mensagens podem não ser entregues.</p>
                            )}

                            <form onSubmit={send} className="flex items-center gap-2 border-t border-gray-100 p-3">
                                <input
                                    type="text"
                                    value={reply.data.body}
                                    onChange={(e) => reply.setData('body', e.target.value)}
                                    placeholder="Digite uma mensagem…"
                                    className="flex-1 rounded-full border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                                />
                                <button type="submit" disabled={reply.processing || !reply.data.body.trim()} className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50">
                                    <Send className="h-4 w-4" />
                                </button>
                            </form>
                        </>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
