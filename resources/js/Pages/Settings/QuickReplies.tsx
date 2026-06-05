import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { MessageSquareText, Plus, Trash2, Pencil, X } from 'lucide-react';

interface Option { value: string; label: string }
interface Reply {
    id: string;
    title: string;
    shortcut: string | null;
    body: string;
    sector_id: string | null;
    sector: string | null;
    sort_order: number;
}
interface Props {
    replies: Reply[];
    sectors: Option[];
}

export default function QuickReplies({ replies, sectors }: Props) {
    const [editing, setEditing] = useState<Reply | 'new' | null>(null);

    const remove = (r: Reply) => {
        if (confirm(`Remover a resposta "${r.title}"?`)) {
            router.delete(route('quick-replies.destroy', r.id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout>
            <Head title="Respostas rápidas" />

            <div className="mx-auto max-w-3xl space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900">
                        <MessageSquareText className="h-6 w-6 text-blue-600" /> Respostas rápidas
                    </h1>
                    <button onClick={() => setEditing('new')} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        <Plus className="h-4 w-4" /> Nova resposta
                    </button>
                </div>

                <p className="text-sm text-gray-500">
                    Mensagens prontas que os atendentes inserem com um clique na inbox. Deixe sem setor para disponibilizar a todos, ou escolha um setor específico.
                </p>

                {replies.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-gray-300 bg-white py-12 text-center text-sm text-gray-400">
                        Nenhuma resposta pronta ainda.
                    </div>
                ) : (
                    <ul className="space-y-3">
                        {replies.map((r) => (
                            <li key={r.id} className="rounded-xl border border-gray-200 bg-white p-4">
                                <div className="flex items-start gap-3">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <span className="font-semibold text-gray-900">{r.title}</span>
                                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">{r.sector ?? 'Todos os setores'}</span>
                                        </div>
                                        <p className="mt-1 whitespace-pre-wrap break-words text-sm text-gray-600">{r.body}</p>
                                    </div>
                                    <button onClick={() => setEditing(r)} className="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700"><Pencil className="h-4 w-4" /></button>
                                    <button onClick={() => remove(r)} className="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600"><Trash2 className="h-4 w-4" /></button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {editing && <ReplyForm reply={editing === 'new' ? null : editing} sectors={sectors} onClose={() => setEditing(null)} />}
        </AppLayout>
    );
}

function ReplyForm({ reply, sectors, onClose }: { reply: Reply | null; sectors: Option[]; onClose: () => void }) {
    const form = useForm({
        title: reply?.title ?? '',
        shortcut: reply?.shortcut ?? '',
        body: reply?.body ?? '',
        sector_id: reply?.sector_id ?? '',
        sort_order: reply?.sort_order ?? 0,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: onClose };
        if (reply) form.put(route('quick-replies.update', reply.id), opts);
        else form.post(route('quick-replies.store'), opts);
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
            <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6" onClick={(e) => e.stopPropagation()}>
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="font-semibold text-gray-900">{reply ? 'Editar resposta' : 'Nova resposta'}</h2>
                    <button onClick={onClose} className="rounded p-1 text-gray-400 hover:bg-gray-100"><X className="h-4 w-4" /></button>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div className="flex gap-3">
                        <div className="flex-1">
                            <label className="mb-1 block text-sm font-medium text-gray-700">Título</label>
                            <input type="text" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="Ex.: Saudação inicial" className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" />
                            {form.errors.title && <p className="mt-1 text-xs text-red-600">{form.errors.title}</p>}
                        </div>
                        <div className="w-24">
                            <label className="mb-1 block text-sm font-medium text-gray-700">Ordem</label>
                            <input type="number" min={0} value={form.data.sort_order} onChange={(e) => form.setData('sort_order', Number(e.target.value))} className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" />
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Setor</label>
                        <select value={form.data.sector_id} onChange={(e) => form.setData('sector_id', e.target.value)} className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Todos os setores</option>
                            {sectors.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                        </select>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Mensagem</label>
                        <textarea value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} rows={4} placeholder="Texto da resposta pronta…" className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" />
                        {form.errors.body && <p className="mt-1 text-xs text-red-600">{form.errors.body}</p>}
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" onClick={onClose} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                        <button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    );
}
