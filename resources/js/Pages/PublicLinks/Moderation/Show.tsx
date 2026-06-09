import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, X } from 'lucide-react';
import AttachmentList, { type Attachment } from '@/Components/AttachmentList';

interface Option { value: string; label: string }

interface Submission {
    id: string;
    type: string;
    type_label: string;
    protocol: string | null;
    status: string;
    status_label: string;
    name: string | null;
    email: string | null;
    phone: string | null;
    document: string | null;
    payload: Record<string, unknown> | null;
    condominium: { id: string; name: string } | null;
    reviewer: { id: string; name: string } | null;
    reviewed_at: string | null;
    review_notes: string | null;
    person: { id: string; name: string } | null;
    occurrence: { id: string; title: string } | null;
    created_at: string | null;
}

interface Props {
    submission: Submission;
    attachments: Attachment[];
    units: Option[];
    relations: Record<string, string>;
    categories: Record<string, string>;
    priorities: Record<string, string>;
    canManage: boolean;
}

export default function ModerationShow({ submission: s, attachments, units, relations, categories, priorities, canManage }: Props) {
    const p = s.payload ?? {};
    const isResident = s.type === 'resident_signup';

    const approve = useForm<{
        unit_id: string;
        relation: string;
        invite: boolean;
        channels: string[];
        priority: string;
    }>({
        unit_id: (p.unit_id as string) ?? '',
        relation: (p.relation as string) ?? 'owner',
        invite: false,
        channels: ['email'],
        priority: 'normal',
    });

    const reject = useForm<{ review_notes: string }>({ review_notes: '' });

    const submitApprove = () => approve.post(route('public-links.moderation.approve', s.id), { preserveScroll: true });
    const submitReject = () => {
        if (confirm('Reprovar este envio?')) reject.post(route('public-links.moderation.reject', s.id), { preserveScroll: true });
    };

    const toggleChannel = (ch: string) => {
        approve.setData('channels', approve.data.channels.includes(ch)
            ? approve.data.channels.filter((c) => c !== ch)
            : [...approve.data.channels, ch]);
    };

    const field = 'mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500';
    const row = (label: string, value: React.ReactNode) => (
        <div>
            <dt className="text-xs text-gray-500">{label}</dt>
            <dd className="text-sm font-medium text-gray-900">{value || '—'}</dd>
        </div>
    );

    return (
        <AppLayout>
            <Head title="Moderar envio" />

            <Link href={route('public-links.moderation.index')} className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <ArrowLeft className="h-4 w-4" /> Voltar à fila
            </Link>

            <div className="grid gap-5 lg:grid-cols-5">
                {/* Dados do envio */}
                <div className="lg:col-span-3">
                    <div className="rounded-xl border border-gray-200 bg-white p-5">
                        <div className="mb-4 flex items-center justify-between">
                            <div>
                                <h2 className="font-semibold text-gray-900">{s.type_label}</h2>
                                {s.protocol && <span className="font-mono text-xs tracking-widest text-gray-400">Protocolo {s.protocol}</span>}
                            </div>
                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{s.status_label}</span>
                        </div>
                        <dl className="grid grid-cols-2 gap-4">
                            {row('Condomínio', s.condominium?.name)}
                            {row('Solicitante', s.name)}
                            {row('Telefone', s.phone)}
                            {row('E-mail', s.email)}
                            {isResident && row('CPF/CNPJ', s.document)}
                            {isResident && row('Vínculo informado', relations[p.relation as string] ?? '—')}
                            {row('Unidade informada', (p.unit_label as string) ?? '—')}
                            {!isResident && row('Categoria', categories[p.category as string] ?? '—')}
                        </dl>

                        {!isResident && (
                            <div className="mt-4 border-t border-gray-100 pt-4">
                                {row('Assunto', p.title as string)}
                                <div className="mt-3">
                                    <dt className="text-xs text-gray-500">Descrição</dt>
                                    <dd className="mt-1 whitespace-pre-line text-sm text-gray-700">{(p.description as string) ?? '—'}</dd>
                                </div>
                            </div>
                        )}

                        {isResident && p.notes ? (
                            <div className="mt-4 border-t border-gray-100 pt-4">
                                {row('Observações', p.notes as string)}
                            </div>
                        ) : null}

                        {attachments.length > 0 && (
                            <div className="mt-4 border-t border-gray-100 pt-4">
                                <dt className="mb-2 text-xs text-gray-500">Fotos enviadas</dt>
                                <AttachmentList attachments={attachments} canRemove={canManage} />
                            </div>
                        )}
                    </div>

                    {s.status !== 'pending' && (
                        <div className="mt-4 rounded-xl border border-gray-200 bg-white p-5 text-sm">
                            <p className="text-gray-500">
                                {s.status_label} por <span className="font-medium text-gray-900">{s.reviewer?.name ?? '—'}</span>
                                {s.reviewed_at ? ` em ${new Date(s.reviewed_at).toLocaleString('pt-BR')}` : ''}.
                            </p>
                            {s.review_notes && <p className="mt-2 text-gray-700">{s.review_notes}</p>}
                            {s.person && <p className="mt-2"><Link href={route('persons.show', s.person.id)} className="font-medium text-blue-600 hover:underline">Ver pessoa: {s.person.name}</Link></p>}
                            {s.occurrence && <p className="mt-2"><Link href={route('occurrences.show', s.occurrence.id)} className="font-medium text-blue-600 hover:underline">Ver ocorrência: {s.occurrence.title}</Link></p>}
                        </div>
                    )}
                </div>

                {/* Ações de moderação */}
                {s.status === 'pending' && canManage && (
                    <div className="lg:col-span-2">
                        <div className="rounded-xl border border-gray-200 bg-white p-5">
                            <h3 className="font-semibold text-gray-900">{isResident ? 'Aprovar cadastro' : 'Aprovar ocorrência'}</h3>

                            {isResident ? (
                                <div className="mt-4 space-y-4">
                                    <div>
                                        <label className="text-sm font-medium text-gray-700">Unidade</label>
                                        <select value={approve.data.unit_id} onChange={(e) => approve.setData('unit_id', e.target.value)} className={field}>
                                            <option value="">Selecione...</option>
                                            {units.map((u) => <option key={u.value} value={u.value}>{u.label}</option>)}
                                        </select>
                                        {approve.errors.unit_id && <p className="mt-1 text-xs text-red-600">{approve.errors.unit_id}</p>}
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-gray-700">Vínculo</label>
                                        <select value={approve.data.relation} onChange={(e) => approve.setData('relation', e.target.value)} className={field}>
                                            {Object.entries(relations).map(([v, t]) => <option key={v} value={v}>{t}</option>)}
                                        </select>
                                    </div>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input type="checkbox" checked={approve.data.invite} onChange={(e) => approve.setData('invite', e.target.checked)} className="h-4 w-4 rounded border-gray-300 text-blue-600" />
                                        <span className="text-gray-700">Enviar convite ao portal agora</span>
                                    </label>
                                    {approve.data.invite && (
                                        <div className="ml-6 flex gap-4 text-sm">
                                            {['email', 'whatsapp'].map((ch) => (
                                                <label key={ch} className="flex items-center gap-1.5">
                                                    <input type="checkbox" checked={approve.data.channels.includes(ch)} onChange={() => toggleChannel(ch)} className="h-4 w-4 rounded border-gray-300 text-blue-600" />
                                                    <span className="capitalize text-gray-700">{ch === 'email' ? 'E-mail' : 'WhatsApp'}</span>
                                                </label>
                                            ))}
                                        </div>
                                    )}
                                    {approve.data.invite && !s.email && (
                                        <p className="text-xs text-amber-600">Sem e-mail no envio: o convite não poderá ser enviado.</p>
                                    )}
                                </div>
                            ) : (
                                <div className="mt-4">
                                    <label className="text-sm font-medium text-gray-700">Prioridade</label>
                                    <select value={approve.data.priority} onChange={(e) => approve.setData('priority', e.target.value)} className={field}>
                                        {Object.entries(priorities).map(([v, t]) => <option key={v} value={v}>{t}</option>)}
                                    </select>
                                </div>
                            )}

                            <button
                                onClick={submitApprove}
                                disabled={approve.processing}
                                className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-60"
                            >
                                <Check className="h-4 w-4" /> Aprovar
                            </button>
                        </div>

                        <div className="mt-4 rounded-xl border border-gray-200 bg-white p-5">
                            <h3 className="font-semibold text-gray-900">Reprovar</h3>
                            <textarea
                                value={reject.data.review_notes}
                                onChange={(e) => reject.setData('review_notes', e.target.value)}
                                rows={2}
                                placeholder="Motivo (opcional)"
                                className={field}
                            />
                            <button
                                onClick={submitReject}
                                disabled={reject.processing}
                                className="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-red-300 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50 disabled:opacity-60"
                            >
                                <X className="h-4 w-4" /> Reprovar
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
