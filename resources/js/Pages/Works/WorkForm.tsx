import { Link, router } from '@inertiajs/react';
import { Paperclip, Trash2 } from 'lucide-react';

export interface Option { value: string; label: string }

export interface ProposalOption extends Option {
    condominium_id: string | null;
    supplier_id: string | null;
    amount: string | null;
}

export interface Attachment {
    id: string;
    original_filename: string | null;
    file_size_bytes: number;
}

export interface WorkFormData {
    condominium_id: string;
    supplier_id: string;
    quotation_proposal_id: string;
    title: string;
    type: string;
    status: string;
    priority: string;
    description: string;
    start_date: string;
    expected_end_date: string;
    budget_amount: string;
    final_amount: string;
    progress_percent: string;
    responsible_name: string;
    notes: string;
    attachments: File[];
}

interface Props {
    data: WorkFormData;
    setData: (key: keyof WorkFormData, value: string | File[]) => void;
    errors: Partial<Record<keyof WorkFormData | 'attachments', string>>;
    processing: boolean;
    onSubmit: () => void;
    submitLabel: string;
    backHref: string;
    types: Record<string, string>;
    statuses: Record<string, string>;
    priorities: Record<string, string>;
    condominiums: Option[];
    suppliers: Option[];
    approvedProposals: ProposalOption[];
    existingAttachments?: Attachment[];
}

const field = 'w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
            {children}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

const fmtSize = (bytes: number) => `${(bytes / 1024 / 1024).toFixed(1)} MB`;

export default function WorkForm({
    data,
    setData,
    errors,
    processing,
    onSubmit,
    submitLabel,
    backHref,
    types,
    statuses,
    priorities,
    condominiums,
    suppliers,
    approvedProposals,
    existingAttachments = [],
}: Props) {
    const selectProposal = (proposalId: string) => {
        setData('quotation_proposal_id', proposalId);
        const proposal = approvedProposals.find((item) => item.value === proposalId);
        if (!proposal) return;

        if (proposal.condominium_id) setData('condominium_id', proposal.condominium_id);
        if (!data.supplier_id && proposal.supplier_id) setData('supplier_id', proposal.supplier_id);
        if (!data.budget_amount && proposal.amount) setData('budget_amount', proposal.amount);
    };

    const removeAttachment = (attachment: Attachment) => {
        if (confirm(`Remover o anexo "${attachment.original_filename ?? 'arquivo'}"?`)) {
            router.delete(route('attachments.destroy', attachment.id), { preserveScroll: true });
        }
    };

    return (
        <div className="mx-auto max-w-5xl space-y-6">
            <div className="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <Field label="Título *" error={errors.title}>
                    <input value={data.title} onChange={(e) => setData('title', e.target.value)} className={field} maxLength={160} placeholder="Ex.: Reforma da fachada" />
                </Field>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-4">
                    <Field label="Condomínio *" error={errors.condominium_id}>
                        <select value={data.condominium_id} onChange={(e) => setData('condominium_id', e.target.value)} className={field}>
                            <option value="">Selecione...</option>
                            {condominiums.map((condominium) => <option key={condominium.value} value={condominium.value}>{condominium.label}</option>)}
                        </select>
                    </Field>
                    <Field label="Tipo *" error={errors.type}>
                        <select value={data.type} onChange={(e) => setData('type', e.target.value)} className={field}>
                            {Object.entries(types).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                    </Field>
                    <Field label="Status *" error={errors.status}>
                        <select value={data.status} onChange={(e) => setData('status', e.target.value)} className={field}>
                            {Object.entries(statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                    </Field>
                    <Field label="Prioridade *" error={errors.priority}>
                        <select value={data.priority} onChange={(e) => setData('priority', e.target.value)} className={field}>
                            {Object.entries(priorities).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                    </Field>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <Field label="Fornecedor" error={errors.supplier_id}>
                        <select value={data.supplier_id} onChange={(e) => setData('supplier_id', e.target.value)} className={field}>
                            <option value="">Sem fornecedor definido</option>
                            {suppliers.map((supplier) => <option key={supplier.value} value={supplier.value}>{supplier.label}</option>)}
                        </select>
                    </Field>
                    <Field label="Proposta aprovada de orçamento" error={errors.quotation_proposal_id}>
                        <select value={data.quotation_proposal_id} onChange={(e) => selectProposal(e.target.value)} className={field}>
                            <option value="">Sem vínculo com orçamento</option>
                            {approvedProposals.map((proposal) => <option key={proposal.value} value={proposal.value}>{proposal.label}</option>)}
                        </select>
                    </Field>
                </div>

                <Field label="Escopo / descrição" error={errors.description}>
                    <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={5} className={`${field} resize-none`} placeholder="Descreva escopo, local, restrições, aprovações necessárias e entregáveis." />
                </Field>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Field label="Início previsto" error={errors.start_date}>
                        <input type="date" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} className={field} />
                    </Field>
                    <Field label="Conclusão prevista" error={errors.expected_end_date}>
                        <input type="date" value={data.expected_end_date} onChange={(e) => setData('expected_end_date', e.target.value)} className={field} />
                    </Field>
                    <Field label="Orçamento (R$)" error={errors.budget_amount}>
                        <input type="number" min="0" step="0.01" value={data.budget_amount} onChange={(e) => setData('budget_amount', e.target.value)} className={field} />
                    </Field>
                    <Field label="Custo final (R$)" error={errors.final_amount}>
                        <input type="number" min="0" step="0.01" value={data.final_amount} onChange={(e) => setData('final_amount', e.target.value)} className={field} />
                    </Field>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-[180px_minmax(0,1fr)]">
                    <Field label="Progresso (%)" error={errors.progress_percent}>
                        <input type="number" min="0" max="100" value={data.progress_percent} onChange={(e) => setData('progress_percent', e.target.value)} className={field} />
                    </Field>
                    <Field label="Responsável interno" error={errors.responsible_name}>
                        <input value={data.responsible_name} onChange={(e) => setData('responsible_name', e.target.value)} className={field} maxLength={150} placeholder="Nome do síndico, gestor ou responsável pelo acompanhamento" />
                    </Field>
                </div>

                <Field label="Observações internas" error={errors.notes}>
                    <textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} rows={3} className={`${field} resize-none`} />
                </Field>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Field label="Novos anexos" error={errors.attachments}>
                        <input type="file" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx" onChange={(e) => setData('attachments', Array.from(e.target.files ?? []))} className="w-full text-sm text-gray-600" />
                    </Field>

                    {existingAttachments.length > 0 && (
                        <div>
                            <p className="mb-1 text-sm font-medium text-gray-700">Anexos atuais</p>
                            <div className="space-y-1 rounded-lg border border-gray-100 p-3">
                                {existingAttachments.map((attachment) => (
                                    <div key={attachment.id} className="flex items-center justify-between gap-2 text-xs">
                                        <a href={route('attachments.download', attachment.id)} className="inline-flex min-w-0 items-center gap-1 text-blue-600 hover:underline">
                                            <Paperclip className="h-3.5 w-3.5 flex-shrink-0" />
                                            <span className="truncate">{attachment.original_filename ?? 'arquivo'}</span>
                                        </a>
                                        <div className="flex flex-shrink-0 items-center gap-2 text-gray-400">
                                            <span>{fmtSize(attachment.file_size_bytes)}</span>
                                            <button type="button" onClick={() => removeAttachment(attachment)} className="rounded p-1 hover:bg-red-50 hover:text-red-500">
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <div className="flex justify-between">
                <Link href={backHref} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</Link>
                <button type="button" onClick={onSubmit} disabled={processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    {processing ? 'Salvando...' : submitLabel}
                </button>
            </div>
        </div>
    );
}
