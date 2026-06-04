import PortalLayout from '@/Layouts/PortalLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, MessageSquare, Paperclip } from 'lucide-react';
import AttachmentList, { Attachment } from '@/Components/AttachmentList';

interface Comment { id: string; body: string; created_at: string; user: { name: string } | null }
interface Occurrence {
    id: string; title: string; description: string; category: string; priority: string; status: string;
    created_at: string; closed_at: string | null;
    condominium: { name: string } | null; unit: { number: string } | null; assignee: { name: string } | null;
    comments: Comment[];
}
interface Props {
    occurrence: Occurrence;
    attachments: Attachment[];
    categories: Record<string, string>;
    priorities: Record<string, string>;
    statuses: Record<string, string>;
}

const statusStyles: Record<string, string> = {
    open: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-amber-100 text-amber-700',
    closed: 'bg-gray-100 text-gray-600',
};

export default function PortalOccurrenceShow({ occurrence, attachments, categories, priorities, statuses }: Props) {
    const { data, setData, post, processing, reset } = useForm({ body: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('portal.occurrences.comments.store', occurrence.id), {
            preserveScroll: true,
            onSuccess: () => reset('body'),
        });
    };

    return (
        <PortalLayout>
            <Head title={occurrence.title} />

            <Link href={route('portal.occurrences.index')} className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <ArrowLeft className="h-4 w-4" /> Ocorrências
            </Link>

            <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <div className="border-b border-gray-100 p-5">
                    <div className="flex items-start justify-between gap-3">
                        <h1 className="text-lg font-bold text-gray-900">{occurrence.title}</h1>
                        <span className={`flex-shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold ${statusStyles[occurrence.status] ?? 'bg-gray-100 text-gray-600'}`}>
                            {statuses[occurrence.status] ?? occurrence.status}
                        </span>
                    </div>
                    <p className="mt-1 text-xs text-gray-500">
                        {categories[occurrence.category]} · {priorities[occurrence.priority]}
                        {occurrence.unit?.number ? ` · Unid. ${occurrence.unit.number}` : ''} · Aberta em {new Date(occurrence.created_at).toLocaleDateString('pt-BR')}
                    </p>
                    <p className="mt-3 whitespace-pre-wrap text-sm text-gray-700">{occurrence.description}</p>
                    {occurrence.assignee?.name && (
                        <p className="mt-3 text-xs text-gray-500">Responsável: <span className="font-medium text-gray-700">{occurrence.assignee.name}</span></p>
                    )}

                    {attachments.length > 0 && (
                        <div className="mt-4">
                            <p className="mb-2 flex items-center gap-1.5 text-sm font-medium text-gray-700">
                                <Paperclip className="h-4 w-4 text-gray-400" /> Anexos
                            </p>
                            <AttachmentList attachments={attachments} />
                        </div>
                    )}
                </div>

                {/* Comentários */}
                <div className="p-5">
                    <h2 className="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900"><MessageSquare className="h-4 w-4 text-gray-400" /> Conversa</h2>

                    <div className="space-y-3">
                        {occurrence.comments.length === 0 && <p className="text-sm text-gray-400">Nenhuma mensagem ainda.</p>}
                        {occurrence.comments.map((c) => (
                            <div key={c.id} className="rounded-lg bg-gray-50 p-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-medium text-gray-700">{c.user?.name ?? 'Sistema'}</span>
                                    <span className="text-[11px] text-gray-400">{new Date(c.created_at).toLocaleString('pt-BR')}</span>
                                </div>
                                <p className="mt-1 whitespace-pre-wrap text-sm text-gray-700">{c.body}</p>
                            </div>
                        ))}
                    </div>

                    {occurrence.status !== 'closed' && (
                        <form onSubmit={submit} className="mt-4 space-y-2">
                            <textarea
                                value={data.body}
                                onChange={(e) => setData('body', e.target.value)}
                                rows={3}
                                placeholder="Escreva uma mensagem…"
                                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            />
                            <div className="flex justify-end">
                                <button type="submit" disabled={processing || !data.body.trim()} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                                    {processing ? 'Enviando…' : 'Enviar'}
                                </button>
                            </div>
                        </form>
                    )}
                </div>
            </div>
        </PortalLayout>
    );
}
