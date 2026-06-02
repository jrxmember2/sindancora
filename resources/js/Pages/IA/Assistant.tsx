import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Sparkles, Send, Plus, Trash2, TrendingDown, Megaphone, Copy, Check } from 'lucide-react';
import { useState } from 'react';

interface ConversationRow { id: string; title: string | null; updated_at: string }
interface Message { role: string; content: string; created_at: string | null }
interface Draft { title: string; body: string }
interface Props {
    configured: boolean;
    conversations: ConversationRow[];
    conversation: { id: string; title: string | null } | null;
    messages: Message[];
    draft: Draft | null;
}

export default function Assistant({ configured, conversations, conversation, messages, draft }: Props) {
    const convId = conversation?.id ?? null;
    const { data, setData, post, processing, reset } = useForm({ conversation_id: convId, message: '' });
    const [copied, setCopied] = useState(false);

    const send = (e: React.FormEvent) => {
        e.preventDefault();
        if (!data.message.trim()) return;
        post(route('assistant.message'), { onSuccess: () => reset('message') });
    };

    const quick = (routeName: string, extra: Record<string, string> = {}) =>
        router.post(route(routeName), { conversation_id: convId, ...extra }, { preserveScroll: true });

    const draftAnnouncement = () => {
        const prompt = window.prompt('Sobre o que é o comunicado?');
        if (prompt) quick('assistant.announcement', { prompt });
    };

    const copyDraft = () => {
        if (!draft) return;
        navigator.clipboard.writeText(`${draft.title}\n\n${draft.body}`);
        setCopied(true);
        setTimeout(() => setCopied(false), 1500);
    };

    const removeConversation = (id: string) => {
        if (confirm('Remover esta conversa?')) router.delete(route('assistant.destroy', id), { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title="Assistente IA" />

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-4">
                {/* Conversas */}
                <aside className="lg:col-span-1">
                    <Link href={route('assistant.index')} className="mb-3 flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        <Plus className="h-4 w-4" /> Nova conversa
                    </Link>
                    <div className="space-y-1">
                        {conversations.length === 0 && <p className="px-2 py-3 text-xs text-gray-400">Nenhuma conversa ainda.</p>}
                        {conversations.map((c) => (
                            <div key={c.id} className={`group flex items-center gap-1 rounded-lg ${c.id === convId ? 'bg-blue-50' : 'hover:bg-gray-100'}`}>
                                <Link href={route('assistant.index', { conversation: c.id })} className="min-w-0 flex-1 truncate px-3 py-2 text-sm text-gray-700">
                                    {c.title ?? 'Conversa'}
                                </Link>
                                <button onClick={() => removeConversation(c.id)} className="px-2 text-gray-300 opacity-0 hover:text-red-600 group-hover:opacity-100">
                                    <Trash2 className="h-3.5 w-3.5" />
                                </button>
                            </div>
                        ))}
                    </div>
                </aside>

                {/* Chat */}
                <section className="lg:col-span-3">
                    <div className="mb-3 flex items-center gap-2">
                        <Sparkles className="h-5 w-5 text-blue-600" />
                        <h1 className="text-lg font-bold text-gray-900">Assistente IA</h1>
                    </div>

                    {!configured && (
                        <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            A integração de IA ainda não está configurada. Defina <code className="text-xs">ANTHROPIC_API_KEY</code> no ambiente.
                        </div>
                    )}

                    {draft && (
                        <div className="mb-4 rounded-xl border border-blue-200 bg-blue-50 p-4">
                            <p className="text-sm font-semibold text-blue-900">Rascunho de comunicado pronto</p>
                            <p className="mt-1 text-sm font-medium text-gray-900">{draft.title}</p>
                            <p className="mt-1 whitespace-pre-wrap text-sm text-gray-700">{draft.body}</p>
                            <div className="mt-3 flex gap-2">
                                <button onClick={copyDraft} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    {copied ? <Check className="h-4 w-4 text-green-600" /> : <Copy className="h-4 w-4" />} Copiar
                                </button>
                                <Link href={route('announcements.create')} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    <Megaphone className="h-4 w-4" /> Criar comunicado
                                </Link>
                            </div>
                        </div>
                    )}

                    {/* Mensagens */}
                    <div className="mb-3 min-h-[300px] space-y-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        {messages.length === 0 && (
                            <p className="py-12 text-center text-sm text-gray-400">
                                Pergunte algo sobre o condomínio — finanças, ocorrências, reservas ou documentos.
                            </p>
                        )}
                        {messages.map((m, i) => (
                            <div key={i} className={`flex ${m.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                                <div className={`max-w-[85%] whitespace-pre-wrap rounded-2xl px-4 py-2 text-sm ${m.role === 'user' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-800'}`}>
                                    {m.content}
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Ações rápidas */}
                    <div className="mb-3 flex flex-wrap gap-2">
                        <button onClick={() => quick('assistant.delinquency')} disabled={!configured} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                            <TrendingDown className="h-4 w-4" /> Análise de inadimplência
                        </button>
                        <button onClick={draftAnnouncement} disabled={!configured} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                            <Megaphone className="h-4 w-4" /> Rascunho de comunicado
                        </button>
                    </div>

                    {/* Entrada */}
                    <form onSubmit={send} className="flex gap-2">
                        <input
                            value={data.message}
                            onChange={(e) => setData('message', e.target.value)}
                            disabled={!configured || processing}
                            placeholder="Escreva sua pergunta…"
                            className="flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:bg-gray-50"
                        />
                        <button type="submit" disabled={!configured || processing || !data.message.trim()} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                            <Send className="h-4 w-4" /> {processing ? 'Enviando…' : 'Enviar'}
                        </button>
                    </form>
                </section>
            </div>
        </AppLayout>
    );
}
