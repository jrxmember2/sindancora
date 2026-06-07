import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, ClipboardCheck, Paperclip, Pencil, Plus, Trash2, XCircle } from 'lucide-react';
import type { PageProps } from '@/types';
import type { Option } from './QuotationForm';

interface Attachment {
    id: string;
    original_filename: string | null;
    file_size_bytes: number;
}

interface Expense {
    id: string;
    status: string;
    due_date: string | null;
    amount: string;
    description: string;
}

interface MaintenancePlan {
    id: string;
    title: string;
}

interface Proposal {
    id: string;
    supplier_id: string | null;
    supplier_name: string;
    amount: string;
    execution_days: number | null;
    valid_until: string | null;
    status: string;
    status_label: string;
    notes: string | null;
    attachments: Attachment[];
    supplier: { id: string; name: string } | null;
    expense: Expense | null;
    maintenance_plan: MaintenancePlan | null;
}

interface Quotation {
    id: string;
    title: string;
    category: string | null;
    description: string | null;
    status: string;
    status_label: string;
    response_deadline: string | null;
    approved_at: string | null;
    approved_proposal_id: string | null;
    notes: string | null;
    condominium: { id: string; name: string } | null;
    creator: { id: string; name: string } | null;
    approver: { id: string; name: string } | null;
    proposals: Proposal[];
}

interface Props {
    quotation: Quotation;
    categories: Record<string, string>;
    proposalStatuses: Record<string, string>;
    suppliers: Option[];
    frequencies: Record<string, string>;
    canGenerateExpense: boolean;
    canCreateMaintenance: boolean;
}

const today = new Date().toISOString().slice(0, 10);
const brl = (value: string | number) => Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const fmtDate = (value: string | null) => value ? new Date(value.slice(0, 10) + 'T00:00:00').toLocaleDateString('pt-BR') : '-';
const fmtSize = (bytes: number) => `${(bytes / 1024 / 1024).toFixed(1)} MB`;

const statusClass: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-600',
    collecting: 'bg-blue-50 text-blue-700',
    approved: 'bg-emerald-50 text-emerald-700',
    rejected: 'bg-red-50 text-red-700',
    cancelled: 'bg-gray-100 text-gray-500',
};

const proposalStatusClass: Record<string, string> = {
    received: 'bg-blue-50 text-blue-700',
    approved: 'bg-emerald-50 text-emerald-700',
    rejected: 'bg-gray-100 text-gray-500',
};

export default function QuotationShow({
    quotation,
    categories,
    proposalStatuses,
    suppliers,
    frequencies,
    canGenerateExpense,
    canCreateMaintenance,
}: Props) {
    const { auth, tenant } = usePage<PageProps>().props;
    const permissions = auth.user?.permissions ?? [];
    const can = (permission: string) => permissions.includes('*') || permissions.includes(permission);
    const planAllows = (module: string) => auth.user?.is_super_admin || (tenant?.plan?.modules ?? []).includes(module);
    const canOpenExpenses = can('expenses:read') && planAllows('financial');
    const canEditExpenses = can('expenses:update') && planAllows('financial');
    const canOpenMaintenance = can('maintenance:read') && planAllows('maintenance');
    const isClosed = ['approved', 'rejected', 'cancelled'].includes(quotation.status);

    const proposalForm = useForm({
        supplier_id: '',
        amount: '',
        execution_days: '',
        valid_until: '',
        notes: '',
        attachments: [] as File[],
    });

    const approvalForm = useForm({
        generate_expense: false,
        expense_due_date: today,
        expense_document_number: '',
        expense_reminder_days: '3',
        generate_maintenance: false,
        maintenance_frequency: 'once',
        maintenance_next_due_date: today,
        maintenance_alert_days: '15',
    });

    const [selectedProposal, setSelectedProposal] = useState<Proposal | null>(null);

    const submitProposal = (event: React.FormEvent) => {
        event.preventDefault();
        proposalForm.post(route('quotations.proposals.store', quotation.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => proposalForm.reset(),
        });
    };

    const startApproval = (proposal: Proposal) => {
        setSelectedProposal(proposal);
        approvalForm.setData('generate_expense', false);
        approvalForm.setData('generate_maintenance', false);
        approvalForm.setData('expense_due_date', today);
        approvalForm.setData('maintenance_next_due_date', today);
    };

    const approve = (event: React.FormEvent) => {
        event.preventDefault();
        if (!selectedProposal) return;

        approvalForm.post(route('quotations.proposals.approve', selectedProposal.id), {
            preserveScroll: true,
            onSuccess: () => setSelectedProposal(null),
        });
    };

    const removeProposal = (proposal: Proposal) => {
        if (confirm(`Remover a proposta de ${proposal.supplier_name}?`)) {
            router.delete(route('quotations.proposals.destroy', proposal.id), { preserveScroll: true });
        }
    };

    const removeAttachment = (attachment: Attachment) => {
        if (confirm(`Remover o anexo "${attachment.original_filename ?? 'arquivo'}"?`)) {
            router.delete(route('attachments.destroy', attachment.id), { preserveScroll: true });
        }
    };

    const rejectQuotation = () => {
        if (confirm('Reprovar este orçamento e rejeitar as propostas recebidas?')) {
            router.post(route('quotations.reject', quotation.id), {}, { preserveScroll: true });
        }
    };

    return (
        <AppLayout>
            <Head title={quotation.title} />
            <div className="mx-auto max-w-6xl space-y-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <Link href={route('quotations.index')} className="text-sm text-gray-500 hover:text-gray-700">← Orçamentos</Link>
                        <div className="mt-1 flex items-center gap-2">
                            <ClipboardCheck className="h-6 w-6 text-blue-600" />
                            <h1 className="text-2xl font-bold text-gray-900">{quotation.title}</h1>
                            <span className={`rounded-full px-2 py-1 text-xs font-medium ${statusClass[quotation.status] ?? 'bg-gray-100 text-gray-600'}`}>
                                {quotation.status_label}
                            </span>
                        </div>
                        <p className="mt-1 text-sm text-gray-500">
                            {quotation.condominium?.name ?? '-'}
                            {quotation.category ? ` · ${categories[quotation.category] ?? quotation.category}` : ''}
                            {quotation.response_deadline ? ` · prazo ${fmtDate(quotation.response_deadline)}` : ''}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {can('quotations:update') && quotation.status !== 'approved' && (
                            <Link href={route('quotations.edit', quotation.id)} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <Pencil className="h-4 w-4" /> Editar
                            </Link>
                        )}
                        {can('quotations:approve') && !isClosed && (
                            <button onClick={rejectQuotation} className="inline-flex items-center gap-2 rounded-lg border border-red-100 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">
                                <XCircle className="h-4 w-4" /> Reprovar
                            </button>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-700">Escopo</h2>
                        {quotation.description ? (
                            <p className="mt-3 whitespace-pre-wrap text-sm text-gray-700">{quotation.description}</p>
                        ) : (
                            <p className="mt-3 text-sm text-gray-400">Sem descrição informada.</p>
                        )}
                        {quotation.notes && (
                            <div className="mt-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-600">
                                <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Observações internas</p>
                                <p className="whitespace-pre-wrap">{quotation.notes}</p>
                            </div>
                        )}
                    </div>

                    <div className="space-y-3 rounded-xl border border-gray-100 bg-white p-5 text-sm shadow-sm">
                        <div>
                            <p className="text-xs uppercase tracking-wide text-gray-500">Criado por</p>
                            <p className="font-medium text-gray-900">{quotation.creator?.name ?? '-'}</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wide text-gray-500">Propostas</p>
                            <p className="font-medium text-gray-900">{quotation.proposals.length}</p>
                        </div>
                        {quotation.approver && (
                            <div>
                                <p className="text-xs uppercase tracking-wide text-gray-500">Aprovado por</p>
                                <p className="font-medium text-gray-900">{quotation.approver.name} · {fmtDate(quotation.approved_at)}</p>
                            </div>
                        )}
                    </div>
                </div>

                {can('quotations:update') && !isClosed && (
                    <form onSubmit={submitProposal} className="space-y-4 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h2 className="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                            <Plus className="h-4 w-4" /> Nova proposta
                        </h2>
                        <div className="grid gap-4 lg:grid-cols-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Fornecedor *</label>
                                <select value={proposalForm.data.supplier_id} onChange={(e) => proposalForm.setData('supplier_id', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                                    <option value="">Selecione...</option>
                                    {suppliers.map((supplier) => <option key={supplier.value} value={supplier.value}>{supplier.label}</option>)}
                                </select>
                                {proposalForm.errors.supplier_id && <p className="mt-1 text-xs text-red-600">{proposalForm.errors.supplier_id}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Valor (R$) *</label>
                                <input type="number" min="0" step="0.01" value={proposalForm.data.amount} onChange={(e) => proposalForm.setData('amount', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                                {proposalForm.errors.amount && <p className="mt-1 text-xs text-red-600">{proposalForm.errors.amount}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Prazo execução</label>
                                <div className="flex rounded-lg border border-gray-200">
                                    <input type="number" min="0" value={proposalForm.data.execution_days} onChange={(e) => proposalForm.setData('execution_days', e.target.value)} className="w-full rounded-l-lg border-0 px-3 py-2 text-sm focus:outline-none focus:ring-0" />
                                    <span className="flex items-center rounded-r-lg bg-gray-50 px-3 text-xs text-gray-500">dias</span>
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Validade</label>
                                <input type="date" value={proposalForm.data.valid_until} onChange={(e) => proposalForm.setData('valid_until', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                            </div>
                        </div>
                        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Observações</label>
                                <textarea value={proposalForm.data.notes} onChange={(e) => proposalForm.setData('notes', e.target.value)} rows={3} className="w-full resize-none rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Anexos</label>
                                <input type="file" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx" onChange={(e) => proposalForm.setData('attachments', Array.from(e.target.files ?? []))} className="w-full text-sm text-gray-600" />
                                {proposalForm.errors.attachments && <p className="mt-1 text-xs text-red-600">{proposalForm.errors.attachments}</p>}
                            </div>
                        </div>
                        <button type="submit" disabled={proposalForm.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                            {proposalForm.processing ? 'Salvando...' : 'Adicionar proposta'}
                        </button>
                    </form>
                )}

                <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div className="border-b border-gray-100 px-5 py-4">
                        <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-700">Comparação de propostas</h2>
                    </div>
                    <table className="w-full text-sm">
                        <thead className="border-b border-gray-100 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Fornecedor</th>
                                <th className="px-4 py-3 text-right">Valor</th>
                                <th className="px-4 py-3">Prazo</th>
                                <th className="px-4 py-3">Validade</th>
                                <th className="px-4 py-3">Anexos</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {quotation.proposals.length === 0 && (
                                <tr><td colSpan={7} className="px-4 py-10 text-center text-sm text-gray-400">Nenhuma proposta registrada.</td></tr>
                            )}
                            {quotation.proposals.map((proposal) => (
                                <tr key={proposal.id} className={proposal.status === 'approved' ? 'bg-emerald-50/40' : 'hover:bg-gray-50'}>
                                    <td className="px-4 py-3">
                                        <p className="font-medium text-gray-900">{proposal.supplier_name}</p>
                                        {proposal.notes && <p className="mt-1 max-w-sm text-xs text-gray-500">{proposal.notes}</p>}
                                        {proposal.expense && (
                                            <p className="mt-1 text-xs text-gray-500">
                                                Conta:{' '}
                                                {canOpenExpenses ? (
                                                    <Link href={canEditExpenses ? route('expenses.edit', proposal.expense.id) : route('expenses.index')} className="text-blue-600 hover:underline">{brl(proposal.expense.amount)}</Link>
                                                ) : brl(proposal.expense.amount)}
                                            </p>
                                        )}
                                        {proposal.maintenance_plan && (
                                            <p className="mt-1 text-xs text-gray-500">
                                                Manutenção:{' '}
                                                {canOpenMaintenance ? (
                                                    <Link href={route('maintenance.show', proposal.maintenance_plan.id)} className="text-blue-600 hover:underline">{proposal.maintenance_plan.title}</Link>
                                                ) : proposal.maintenance_plan.title}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right font-semibold text-gray-900">{brl(proposal.amount)}</td>
                                    <td className="px-4 py-3 text-gray-600">{proposal.execution_days !== null ? `${proposal.execution_days} dia(s)` : '-'}</td>
                                    <td className="px-4 py-3 text-gray-600">{fmtDate(proposal.valid_until)}</td>
                                    <td className="px-4 py-3">
                                        <div className="space-y-1">
                                            {proposal.attachments.length === 0 && <span className="text-xs text-gray-400">-</span>}
                                            {proposal.attachments.map((attachment) => (
                                                <div key={attachment.id} className="flex items-center gap-1 text-xs">
                                                    <a href={route('attachments.download', attachment.id)} className="inline-flex items-center gap-1 text-blue-600 hover:underline">
                                                        <Paperclip className="h-3.5 w-3.5" />
                                                        {attachment.original_filename ?? 'arquivo'}
                                                    </a>
                                                    <span className="text-gray-400">{fmtSize(attachment.file_size_bytes)}</span>
                                                    {can('quotations:update') && !isClosed && (
                                                        <button type="button" onClick={() => removeAttachment(attachment)} className="text-gray-300 hover:text-red-500">
                                                            <Trash2 className="h-3.5 w-3.5" />
                                                        </button>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex rounded-full px-2 py-1 text-xs font-medium ${proposalStatusClass[proposal.status] ?? 'bg-gray-100 text-gray-600'}`}>
                                            {proposalStatuses[proposal.status] ?? proposal.status_label}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            {can('quotations:approve') && !isClosed && proposal.status === 'received' && (
                                                <button onClick={() => startApproval(proposal)} className="rounded p-1 text-gray-400 hover:text-emerald-600" title="Aprovar">
                                                    <CheckCircle2 className="h-4 w-4" />
                                                </button>
                                            )}
                                            {can('quotations:update') && !isClosed && proposal.status !== 'approved' && (
                                                <button onClick={() => removeProposal(proposal)} className="rounded p-1 text-gray-400 hover:text-red-500" title="Remover">
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {selectedProposal && (
                    <form onSubmit={approve} className="space-y-4 rounded-xl border border-emerald-100 bg-emerald-50/50 p-6 shadow-sm">
                        <div>
                            <h2 className="text-sm font-semibold uppercase tracking-wide text-emerald-800">Aprovar proposta</h2>
                            <p className="mt-1 text-sm text-emerald-900">
                                {selectedProposal.supplier_name} · {brl(selectedProposal.amount)}
                            </p>
                        </div>

                        <div className="grid gap-4 lg:grid-cols-2">
                            {canCreateMaintenance && (
                                <div className="rounded-lg border border-white bg-white p-4">
                                    <label className="flex items-center gap-2 text-sm font-medium text-gray-700">
                                        <input type="checkbox" checked={approvalForm.data.generate_maintenance} onChange={(e) => approvalForm.setData('generate_maintenance', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                        Gerar manutenção
                                    </label>
                                    {approvalForm.data.generate_maintenance && (
                                        <div className="mt-3 grid gap-3 sm:grid-cols-3">
                                            <div>
                                                <label className="mb-1 block text-xs font-medium text-gray-600">Recorrência</label>
                                                <select value={approvalForm.data.maintenance_frequency} onChange={(e) => approvalForm.setData('maintenance_frequency', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                                                    {Object.entries(frequencies).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                                </select>
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-medium text-gray-600">Próxima data</label>
                                                <input type="date" value={approvalForm.data.maintenance_next_due_date} onChange={(e) => approvalForm.setData('maintenance_next_due_date', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                                                {approvalForm.errors.maintenance_next_due_date && <p className="mt-1 text-xs text-red-600">{approvalForm.errors.maintenance_next_due_date}</p>}
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-medium text-gray-600">Alerta</label>
                                                <input type="number" min={0} max={365} value={approvalForm.data.maintenance_alert_days} onChange={(e) => approvalForm.setData('maintenance_alert_days', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}

                            {canGenerateExpense && (
                                <div className="rounded-lg border border-white bg-white p-4">
                                    <label className="flex items-center gap-2 text-sm font-medium text-gray-700">
                                        <input type="checkbox" checked={approvalForm.data.generate_expense} onChange={(e) => approvalForm.setData('generate_expense', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                        Gerar conta a pagar
                                    </label>
                                    {approvalForm.data.generate_expense && (
                                        <div className="mt-3 grid gap-3 sm:grid-cols-3">
                                            <div>
                                                <label className="mb-1 block text-xs font-medium text-gray-600">Vencimento</label>
                                                <input type="date" value={approvalForm.data.expense_due_date} onChange={(e) => approvalForm.setData('expense_due_date', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                                                {approvalForm.errors.expense_due_date && <p className="mt-1 text-xs text-red-600">{approvalForm.errors.expense_due_date}</p>}
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-medium text-gray-600">Nº documento</label>
                                                <input value={approvalForm.data.expense_document_number} onChange={(e) => approvalForm.setData('expense_document_number', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                                            </div>
                                            <div>
                                                <label className="mb-1 block text-xs font-medium text-gray-600">Lembrar com</label>
                                                <input type="number" min={0} max={60} value={approvalForm.data.expense_reminder_days} onChange={(e) => approvalForm.setData('expense_reminder_days', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>

                        <div className="flex justify-end gap-2">
                            <button type="button" onClick={() => setSelectedProposal(null)} className="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button type="submit" disabled={approvalForm.processing} className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                                <CheckCircle2 className="h-4 w-4" />
                                {approvalForm.processing ? 'Aprovando...' : 'Confirmar aprovação'}
                            </button>
                        </div>
                    </form>
                )}

                {quotation.status === 'approved' && (
                    <div className="rounded-xl border border-emerald-100 bg-emerald-50 p-5 text-sm text-emerald-900">
                        <div className="flex items-center gap-2 font-medium">
                            <CheckCircle2 className="h-4 w-4" />
                            Orçamento aprovado.
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
