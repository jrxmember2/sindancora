import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Building2, Check, Copy, FileText, Megaphone, Plus, Scale, Send, Sparkles, Trash2, TrendingDown } from 'lucide-react';
import { useEffect, useState } from 'react';

interface CondominiumRef { id: string; name: string }
interface CondominiumOption { value: string; label: string }
interface ConversationRow {
    id: string;
    title: string | null;
    condominium_id: string | null;
    condominium: CondominiumRef | null;
    updated_at: string;
}
interface MessageSource {
    label: string;
    type: string;
    id: string;
    title: string;
    category: string;
    scope: string;
}
interface Message { role: string; content: string; sources: MessageSource[] | null; created_at: string | null }
interface Draft { title: string; body: string }
interface AiUsage {
    current: number;
    limit: number;
    unlimited: boolean;
    remaining: number | null;
    exhausted: boolean;
    reset_at: string | null;
}
interface Props {
    configured: boolean;
    conversations: ConversationRow[];
    conversation: { id: string; title: string | null; condominium_id: string | null; condominium: CondominiumRef | null } | null;
    messages: Message[];
    condominiums: CondominiumOption[];
    selectedCondominiumId: string | null;
    requiresCondominium: boolean;
    draft: Draft | null;
    aiUsage: AiUsage;
}

export default function Assistant({
    configured,
    conversations,
    conversation,
    messages,
    condominiums,
    selectedCondominiumId,
    requiresCondominium,
    draft,
    aiUsage,
}: Props) {
    const convId = conversation?.id ?? null;
    const conversationScopeId = conversation?.condominium_id ?? null;
    const { data, setData, post, processing, reset } = useForm<{
        conversation_id: string | null;
        condominium_id: string;
        message: string;
    }>({
        conversation_id: convId,
        condominium_id: selectedCondominiumId ?? '',
        message: '',
    });
    const [copied, setCopied] = useState(false);
    const resetAt = aiUsage.reset_at ? new Date(aiUsage.reset_at).toLocaleDateString('pt-BR') : null;
    const missingCondominium = condominiums.length === 0 || requiresCondominium;
    const blocked = !configured || aiUsage.exhausted || missingCondominium;

    useEffect(() => {
        setData('conversation_id', convId);
        setData('condominium_id', selectedCondominiumId ?? '');
    }, [convId, selectedCondominiumId]);

    const send = (e: React.FormEvent) => {
        e.preventDefault();
        if (blocked || !data.message.trim()) return;
        post(route('assistant.message'), { onSuccess: () => reset('message') });
    };

    const chooseCondominium = (value: string) => {
        setData('condominium_id', value);
        const params: Record<string, string> = {};
        if (convId) params.conversation = convId;
        if (value) params.condominium_id = value;
        router.get(route('assistant.index'), params, { preserveScroll: true });
    };

    const quick = (routeName: string, extra: Record<string, string> = {}) => {
        if (blocked) return;
        router.post(route(routeName), {
            conversation_id: convId,
            condominium_id: data.condominium_id,
            ...extra,
        }, { preserveScroll: true });
    };

    const draftAnnouncement = () => {
        if (blocked) return;
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

    const newConversationParams = data.condominium_id ? { condominium_id: data.condominium_id } : {};
    const placeholder = missingCondominium ? 'Selecione um condomínio para usar o assistente' : 'Escreva sua pergunta...';

    return (
        <AppLayout>
            <Head title="Assistente IA" />

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-4">
                <aside className="lg:col-span-1">
                    <Link href={route('assistant.index', newConversationParams)} className="mb-3 flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        <Plus className="h-4 w-4" /> Nova conversa
                    </Link>
                    <div className="space-y-1">
                        {conversations.length === 0 && <p className="px-2 py-3 text-xs text-gray-400">Nenhuma conversa ainda.</p>}
                        {conversations.map((c) => {
                            const linkParams = c.condominium_id || !data.condominium_id
                                ? { conversation: c.id }
                                : { conversation: c.id, condominium_id: data.condominium_id };

                            return (
                                <div key={c.id} className={`group flex items-center gap-1 rounded-lg ${c.id === convId ? 'bg-blue-50' : 'hover:bg-gray-100'}`}>
                                    <Link href={route('assistant.index', linkParams)} className="min-w-0 flex-1 px-3 py-2 text-sm text-gray-700">
                                        <span className="block truncate">{c.title ?? 'Conversa'}</span>
                                        <span className="mt-0.5 block truncate text-[11px] text-gray-400">{c.condominium?.name ?? 'Sem condomínio definido'}</span>
                                    </Link>
                                    <button onClick={() => removeConversation(c.id)} className="px-2 text-gray-300 opacity-0 hover:text-red-600 group-hover:opacity-100">
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                </aside>

                <section className="lg:col-span-3">
                    <div className="mb-3 flex flex-wrap items-center gap-2">
                        <Sparkles className="h-5 w-5 text-blue-600" />
                        <h1 className="text-lg font-bold text-gray-900">Assistente IA</h1>
                        <div className="ml-auto flex flex-wrap items-center justify-end gap-2">
                            <label className="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-sm text-gray-700">
                                <Building2 className="h-4 w-4 text-gray-400" />
                                <select
                                    value={data.condominium_id}
                                    onChange={(e) => chooseCondominium(e.target.value)}
                                    disabled={!!conversationScopeId || condominiums.length === 0}
                                    className="min-w-44 border-0 bg-transparent p-0 text-sm focus:ring-0 disabled:text-gray-500"
                                >
                                    <option value="">Selecionar condomínio</option>
                                    {condominiums.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                                </select>
                            </label>
                            <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${aiUsage.exhausted ? 'bg-red-100 text-red-700' : 'bg-blue-50 text-blue-700'}`}>
                                {aiUsage.unlimited ? `${aiUsage.current} usadas` : `${aiUsage.remaining} restantes`}
                            </span>
                        </div>
                    </div>

                    {!configured && (
                        <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            A integração global de IA ainda não está ativa. Configure provedor, modelo e chave no Painel de Administração em Admin &gt; IA.
                        </div>
                    )}

                    {configured && condominiums.length === 0 && (
                        <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            Nenhum condomínio ativo está disponível para o seu usuário.
                        </div>
                    )}

                    {configured && condominiums.length > 0 && requiresCondominium && (
                        <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            Selecione um condomínio para iniciar ou continuar esta conversa.
                        </div>
                    )}

                    {configured && aiUsage.exhausted && (
                        <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            O limite mensal de IA foi atingido. {resetAt ? `A renovação está prevista para ${resetAt}.` : 'Ajuste o limite no plano ou no perfil do tenant.'}
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

                    <div className="mb-3 min-h-[300px] space-y-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        {messages.length === 0 && (
                            <p className="py-12 text-center text-sm text-gray-400">
                                Pergunte algo sobre o condomínio selecionado: finanças, ocorrências, reservas ou documentos.
                            </p>
                        )}
                        {messages.map((m, i) => (
                            <div key={i} className={`flex ${m.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                                <div className={`max-w-[85%] rounded-2xl px-4 py-2 text-sm ${m.role === 'user' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-800'}`}>
                                    <div className="whitespace-pre-wrap">{m.content}</div>
                                    {m.role === 'assistant' && m.sources && m.sources.length > 0 && (
                                        <div className="mt-3 border-t border-gray-200 pt-2">
                                            <p className="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500">Fontes consultadas</p>
                                            <div className="flex flex-wrap gap-1.5">
                                                {m.sources.map((source, index) => {
                                                    const SourceIcon = source.type === 'legal' ? Scale : FileText;

                                                    return (
                                                        <span key={`${source.label}-${source.id}-${index}`} className="inline-flex max-w-full items-center gap-1 rounded-md border border-gray-200 bg-white px-2 py-1 text-[11px] text-gray-600">
                                                            <SourceIcon className="h-3 w-3 shrink-0 text-gray-400" />
                                                            <span className="font-semibold text-gray-700">{source.label}</span>
                                                            <span className="truncate">{source.title}</span>
                                                            <span className="text-gray-400">({source.category})</span>
                                                        </span>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="mb-3 flex flex-wrap gap-2">
                        <button onClick={() => quick('assistant.delinquency')} disabled={blocked} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                            <TrendingDown className="h-4 w-4" /> Análise de inadimplência
                        </button>
                        <button onClick={draftAnnouncement} disabled={blocked} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                            <Megaphone className="h-4 w-4" /> Rascunho de comunicado
                        </button>
                    </div>

                    <form onSubmit={send} className="flex gap-2">
                        <input
                            value={data.message}
                            onChange={(e) => setData('message', e.target.value)}
                            disabled={blocked || processing}
                            placeholder={placeholder}
                            className="flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:bg-gray-50"
                        />
                        <button type="submit" disabled={blocked || processing || !data.message.trim()} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                            <Send className="h-4 w-4" /> {processing ? 'Enviando...' : 'Enviar'}
                        </button>
                    </form>
                </section>
            </div>
        </AppLayout>
    );
}
