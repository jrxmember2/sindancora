import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Send, Plus, Trash2, UserX } from 'lucide-react';

interface Campaign {
    id: string;
    name: string;
    status: string;
    status_label: string;
    condominium: string | null;
    connection: string | null;
    total_recipients: number;
    sent_count: number;
    failed_count: number;
    skipped_count: number;
    scheduled_at: string | null;
    created_at: string | null;
}
interface Props { campaigns: Campaign[] }

const statusStyle: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-600',
    scheduled: 'bg-amber-100 text-amber-700',
    sending: 'bg-blue-100 text-blue-700',
    completed: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-600',
};

const time = (iso: string | null) => (iso ? new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '');

export default function CampaignsIndex({ campaigns }: Props) {
    const remove = (c: Campaign) => {
        if (confirm(`Remover a campanha "${c.name}"?`)) {
            router.delete(route('campaigns.destroy', c.id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout>
            <Head title="Disparos de WhatsApp" />

            <div className="mx-auto max-w-5xl space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900">
                        <Send className="h-6 w-6 text-blue-600" /> Disparos de WhatsApp
                    </h1>
                    <div className="flex gap-2">
                        <Link href={route('campaigns.optouts')} className="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <UserX className="h-4 w-4" /> Descadastros
                        </Link>
                        <Link href={route('campaigns.create')} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                            <Plus className="h-4 w-4" /> Nova campanha
                        </Link>
                    </div>
                </div>

                <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Use com responsabilidade: disparos em massa por número não-oficial têm risco de bloqueio. Envie só para quem espera o contato e respeite o descadastro.
                </div>

                {campaigns.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-gray-300 bg-white py-12 text-center text-sm text-gray-400">
                        Nenhuma campanha ainda.
                    </div>
                ) : (
                    <ul className="space-y-3">
                        {campaigns.map((c) => {
                            const done = c.sent_count + c.failed_count + c.skipped_count;
                            const pct = c.total_recipients > 0 ? Math.round((done / c.total_recipients) * 100) : 0;
                            return (
                                <li key={c.id} className="rounded-xl border border-gray-200 bg-white p-4">
                                    <div className="flex items-center gap-3">
                                        <Link href={route('campaigns.show', c.id)} className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-semibold text-gray-900 hover:underline">{c.name}</span>
                                                <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusStyle[c.status] ?? 'bg-gray-100 text-gray-500'}`}>{c.status_label}</span>
                                            </div>
                                            <p className="mt-0.5 truncate text-sm text-gray-500">
                                                {c.condominium ?? '—'}{c.connection ? ` · ${c.connection}` : ''} · {c.total_recipients} destinatário(s)
                                                {c.scheduled_at ? ` · agendada p/ ${time(c.scheduled_at)}` : ''}
                                            </p>
                                        </Link>
                                        <div className="hidden w-40 sm:block">
                                            <div className="mb-0.5 flex justify-between text-xs text-gray-500">
                                                <span>{c.sent_count} enviadas</span><span>{pct}%</span>
                                            </div>
                                            <div className="h-2 w-full rounded-full bg-gray-100">
                                                <div className="h-2 rounded-full bg-green-500" style={{ width: `${pct}%` }} />
                                            </div>
                                            {c.failed_count > 0 && <p className="mt-0.5 text-[11px] text-red-500">{c.failed_count} falha(s)</p>}
                                        </div>
                                        <button onClick={() => remove(c)} className="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}
