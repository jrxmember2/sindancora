import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Trash2, Building2, User, Calendar, CheckCircle2, MessageSquare, RefreshCw, UserCheck, Paperclip, Sparkles } from 'lucide-react';
import { useState } from 'react';
import type { PageProps } from '@/types';
import AttachmentList, { Attachment } from '@/Components/AttachmentList';

interface Option { value: string; label: string }
interface Named { id: string; name: string }
interface Comment {
    id: string; type: string; body: string | null; created_at: string; is_internal: boolean;
    user: Named | null; meta: { from?: string; to?: string; assigned_to?: string | null } | null;
}
interface Occurrence {
    id: string; title: string; description: string; category: string; priority: string; status: string;
    created_at: string; closed_at: string | null; assigned_to: string | null;
    due_at: string | null; sla_status: string | null;
    condominium: Named | null; unit: { id: string; number: string } | null;
    creator: Named | null; assignee: Named | null; comments: Comment[];
}
interface Props {
    occurrence: Occurrence;
    attachments: Attachment[];
    assignableUsers: Option[];
    categories: Record<string, string>;
    priorities: Record<string, string>;
    statuses: Record<string, string>;
    canDraftAi?: boolean;
}

const priorityStyle: Record<string, string> = {
    low: 'bg-gray-100 text-gray-600', normal: 'bg-blue-50 text-blue-700',
    high: 'bg-orange-50 text-orange-700', urgent: 'bg-red-50 text-red-700',
};
const statusStyle: Record<string, string> = {
    open: 'bg-amber-50 text-amber-700', in_progress: 'bg-blue-50 text-blue-700', closed: 'bg-green-50 text-green-700',
};
const fmt = (iso: string | null) => (iso ? new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '');

const slaBadge: Record<string, { label: string; cls: string }> = {
    overdue: { label: 'SLA estourado', cls: 'bg-red-50 text-red-700' },
    due_soon: { label: 'Vence em breve', cls: 'bg-amber-50 text-amber-700' },
    on_time: { label: 'No prazo', cls: 'bg-green-50 text-green-700' },
};

export default function OccurrenceShow({ occurrence: o, attachments, assignableUsers, categories, priorities, statuses, canDraftAi }: Props) {
    const { auth } = usePage<PageProps>().props;
    const perms = auth.user?.permissions ?? [];
    const can = (p: string) => perms.includes('*') || perms.includes(p);

    const commentForm = useForm({ body: '', is_internal: true });
    const [drafting, setDrafting] = useState(false);
    const [draftError, setDraftError] = useState<string | null>(null);

    const draftWithAi = async () => {
        setDrafting(true);
        setDraftError(null);
        try {
            const { data } = await window.axios.post(route('occurrences.draft-reply', o.id));
            commentForm.setData('body', data.text ?? '');
        } catch (e: unknown) {
            const err = e as { response?: { data?: { message?: string } } };
            setDraftError(err.response?.data?.message ?? 'Não foi possível gerar a sugestão agora.');
        } finally {
            setDrafting(false);
        }
    };

    const changeStatus = (status: string) => router.post(route('occurrences.status', o.id), { status }, { preserveScroll: true });
    const assign = (userId: string) => router.post(route('occurrences.assign', o.id), { assigned_to: userId }, { preserveScroll: true });
    const submitComment = () => commentForm.post(route('occurrences.comments.store', o.id), {
        preserveScroll: true,
        onSuccess: () => commentForm.setData('body', ''),
    });
    const destroy = () => { if (confirm('Excluir esta ocorrência?')) router.delete(route('occurrences.destroy', o.id)); };

    const timelineText = (c: Comment): string => {
        const who = c.user?.name ?? 'Sistema';
        if (c.type === 'status') return `${who} alterou o status: ${statuses[c.meta?.from ?? ''] ?? c.meta?.from} → ${statuses[c.meta?.to ?? ''] ?? c.meta?.to}`;
        if (c.type === 'assignment') return c.meta?.assigned_to ? `${who} atribuiu um responsável.` : `${who} removeu o responsável.`;
        return who;
    };

    return (
        <AppLayout>
            <Head title={o.title} />
            <div className="mx-auto max-w-3xl space-y-4">
                <div className="flex items-center justify-between">
                    <Link href={route('occurrences.index')} className="text-sm text-gray-500 hover:text-gray-700">← Ocorrências</Link>
                    <div className="flex gap-2">
                        {can('occurrences:update') && (
                            <Link href={route('occurrences.edit', o.id)} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                                <Pencil className="h-4 w-4" /> Editar
                            </Link>
                        )}
                        {can('occurrences:delete') && (
                            <button onClick={destroy} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-red-600 transition-colors hover:bg-red-50">
                                <Trash2 className="h-4 w-4" /> Excluir
                            </button>
                        )}
                    </div>
                </div>

                {/* Cabeçalho */}
                <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div className="mb-3 flex flex-wrap items-center gap-2">
                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusStyle[o.status] ?? ''}`}>{statuses[o.status] ?? o.status}</span>
                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${priorityStyle[o.priority] ?? ''}`}>{priorities[o.priority] ?? o.priority}</span>
                        <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{categories[o.category] ?? o.category}</span>
                        {o.sla_status && slaBadge[o.sla_status] && (
                            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${slaBadge[o.sla_status].cls}`}>{slaBadge[o.sla_status].label}</span>
                        )}
                    </div>
                    <h1 className="text-2xl font-bold text-gray-900">{o.title}</h1>
                    <div className="mt-3 flex flex-wrap gap-x-5 gap-y-1 border-b border-gray-100 pb-4 text-xs text-gray-500">
                        {o.condominium && <span className="inline-flex items-center gap-1"><Building2 className="h-3.5 w-3.5" /> {o.condominium.name}{o.unit ? ` · ${o.unit.number}` : ''}</span>}
                        {o.creator && <span className="inline-flex items-center gap-1"><User className="h-3.5 w-3.5" /> {o.creator.name}</span>}
                        <span className="inline-flex items-center gap-1"><Calendar className="h-3.5 w-3.5" /> Aberta em {fmt(o.created_at)}</span>
                        {o.due_at && o.status !== 'closed' && <span className={`inline-flex items-center gap-1 ${o.sla_status === 'overdue' ? 'text-red-600' : o.sla_status === 'due_soon' ? 'text-amber-600' : ''}`}><Calendar className="h-3.5 w-3.5" /> Prazo {fmt(o.due_at)}</span>}
                        {o.closed_at && <span className="inline-flex items-center gap-1 text-green-600"><CheckCircle2 className="h-3.5 w-3.5" /> Encerrada em {fmt(o.closed_at)}</span>}
                    </div>
                    <p className="mt-4 whitespace-pre-wrap text-sm leading-relaxed text-gray-700">{o.description}</p>

                    {attachments.length > 0 && (
                        <div className="mt-5 border-t border-gray-100 pt-4">
                            <p className="mb-2 flex items-center gap-1.5 text-sm font-medium text-gray-700">
                                <Paperclip className="h-4 w-4 text-gray-400" /> Anexos
                            </p>
                            <AttachmentList attachments={attachments} canRemove={can('occurrences:update')} />
                        </div>
                    )}
                </div>

                {/* Ações: status + responsável */}
                {can('occurrences:update') && (
                    <div className="flex flex-wrap items-center gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <div className="flex items-center gap-2">
                            <RefreshCw className="h-4 w-4 text-gray-400" />
                            {Object.entries(statuses).map(([value, label]) => {
                                if (value === o.status) return null;
                                const isClose = value === 'closed';
                                if (isClose && !can('occurrences:close')) return null;
                                return (
                                    <button key={value} onClick={() => changeStatus(value)} className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                                        {label}
                                    </button>
                                );
                            })}
                        </div>
                        <div className="ml-auto flex items-center gap-2">
                            <UserCheck className="h-4 w-4 text-gray-400" />
                            <select value={o.assigned_to ?? ''} onChange={e => assign(e.target.value)} className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                                <option value="">Não atribuído</option>
                                {assignableUsers.map(u => <option key={u.value} value={u.value}>{u.label}</option>)}
                            </select>
                        </div>
                    </div>
                )}

                {/* Histórico / comentários */}
                <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 className="mb-4 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                        <MessageSquare className="h-4 w-4" /> Histórico
                    </h2>

                    <div className="space-y-4">
                        {o.comments.length === 0 && <p className="text-sm text-gray-400">Sem atividade ainda.</p>}
                        {o.comments.map(c => (
                            <div key={c.id} className="flex gap-3">
                                <div className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600">
                                    {(c.user?.name ?? 'S').charAt(0).toUpperCase()}
                                </div>
                                <div className="min-w-0 flex-1">
                                    {c.type === 'comment' ? (
                                        <div className={c.is_internal ? 'rounded-lg bg-amber-50/60 px-3 py-2 ring-1 ring-amber-100' : ''}>
                                            <p className="text-sm">
                                                <span className="font-medium text-gray-900">{c.user?.name ?? 'Sistema'}</span>{' '}
                                                <span className="text-xs text-gray-400">{fmt(c.created_at)}</span>
                                                {c.is_internal && <span className="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">nota interna</span>}
                                            </p>
                                            <p className="mt-0.5 whitespace-pre-wrap text-sm text-gray-700">{c.body}</p>
                                        </div>
                                    ) : (
                                        <p className="text-sm text-gray-500">{timelineText(c)} <span className="text-xs text-gray-400">· {fmt(c.created_at)}</span></p>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>

                    {can('occurrences:update') && (
                        <div className="mt-5 border-t border-gray-100 pt-4">
                            <textarea
                                value={commentForm.data.body}
                                onChange={e => commentForm.setData('body', e.target.value)}
                                rows={3}
                                placeholder="Escreva um comentário…"
                                className="w-full resize-none rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            />
                            {commentForm.errors.body && <p className="mt-1 text-xs text-red-600">{commentForm.errors.body}</p>}
                            {draftError && <p className="mt-1 text-xs text-red-600">{draftError}</p>}
                            <label className="mt-2 flex items-center gap-2 text-sm text-gray-600">
                                <input type="checkbox" checked={commentForm.data.is_internal} onChange={e => commentForm.setData('is_internal', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                Nota interna (não visível ao morador)
                            </label>
                            <div className="mt-2 flex items-center justify-between">
                                {canDraftAi ? (
                                    <button onClick={draftWithAi} disabled={drafting} className="inline-flex items-center gap-2 rounded-lg border border-violet-200 bg-violet-50 px-3 py-2 text-sm font-medium text-violet-700 transition-colors hover:bg-violet-100 disabled:opacity-50">
                                        <Sparkles className="h-4 w-4" /> {drafting ? 'Gerando…' : 'Sugerir resposta com IA'}
                                    </button>
                                ) : <span />}
                                <button onClick={submitComment} disabled={commentForm.processing || !commentForm.data.body.trim()} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50">
                                    Comentar
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
