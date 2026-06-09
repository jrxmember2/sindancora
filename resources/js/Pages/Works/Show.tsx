import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CalendarDays, ClipboardCheck, Hammer, Paperclip, Pencil, Plus, Receipt, Trash2, UserRound, Wallet } from 'lucide-react';
import type { PageProps } from '@/types';

interface Attachment {
    id: string;
    original_filename: string | null;
    file_size_bytes: number;
}

interface WorkUpdate {
    id: string;
    title: string;
    description: string | null;
    status: string | null;
    status_label: string | null;
    progress_percent: number | null;
    occurred_at: string | null;
    author: { id: string; name: string } | null;
}

interface Expense {
    id: string;
    status: string;
    display_status: string;
    display_status_label: string;
    due_date: string | null;
    amount: string;
    description: string;
    supplier: string | null;
    supplier_record: { id: string; name: string } | null;
}

interface Work {
    id: string;
    title: string;
    type: string;
    type_label: string;
    status: string;
    status_label: string;
    priority: string;
    priority_label: string;
    description: string | null;
    start_date: string | null;
    expected_end_date: string | null;
    completed_at: string | null;
    budget_amount: string | null;
    final_amount: string | null;
    budget_variance: number | null;
    progress_percent: number;
    responsible_name: string | null;
    notes: string | null;
    condominium: { id: string; name: string } | null;
    supplier: { id: string; name: string } | null;
    quotation: { id: string; title: string } | null;
    quotation_proposal: { id: string; supplier_name: string; amount: string; quotation: { id: string; title: string } | null } | null;
    creator: { id: string; name: string } | null;
    attachments: Attachment[];
    updates: WorkUpdate[];
    expenses: Expense[];
}

interface Props {
    work: Work;
    types: Record<string, string>;
    statuses: Record<string, string>;
    priorities: Record<string, string>;
    expensesTotal: number;
    openExpensesTotal: number;
    canGenerateExpense: boolean;
}

const today = new Date().toISOString().slice(0, 10);
const brl = (value: string | number | null) => Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const date = (value: string | null) => value ? new Date(value.slice(0, 10) + 'T00:00:00').toLocaleDateString('pt-BR') : '-';
const size = (bytes: number) => `${(bytes / 1024 / 1024).toFixed(1)} MB`;

const statusClass: Record<string, string> = {
    planned: 'bg-gray-100 text-gray-600',
    budgeting: 'bg-amber-50 text-amber-700',
    approved: 'bg-blue-50 text-blue-700',
    in_progress: 'bg-emerald-50 text-emerald-700',
    paused: 'bg-orange-50 text-orange-700',
    completed: 'bg-slate-100 text-slate-700',
    cancelled: 'bg-red-50 text-red-700',
};

function Info({ label, value, icon: Icon }: { label: string; value: React.ReactNode; icon: React.ElementType }) {
    return (
        <div className="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
            <div className="flex items-start gap-3">
                <div className="rounded-lg bg-blue-50 p-2 text-blue-600">
                    <Icon className="h-4 w-4" />
                </div>
                <div className="min-w-0">
                    <p className="text-xs font-medium uppercase text-gray-500">{label}</p>
                    <div className="mt-1 text-sm font-semibold text-gray-900">{value}</div>
                </div>
            </div>
        </div>
    );
}

export default function WorkShow({ work, statuses, expensesTotal, openExpensesTotal, canGenerateExpense }: Props) {
    const { auth, tenant } = usePage<PageProps>().props;
    const permissions = auth.user?.permissions ?? [];
    const can = (permission: string) => permissions.includes('*') || permissions.includes(permission);
    const planAllows = (module: string) => auth.user?.is_super_admin || (tenant?.plan?.modules ?? []).includes(module);
    const canOpenExpenses = can('expenses:read') && planAllows('financial');
    const canEditExpenses = can('expenses:update') && planAllows('financial');
    const canOpenQuotations = can('quotations:read') && planAllows('quotations');
    const canOpenSuppliers = can('suppliers:read') && planAllows('suppliers');

    const updateForm = useForm({
        title: '',
        description: '',
        status: '',
        progress_percent: String(work.progress_percent ?? 0),
        occurred_at: today,
    });

    const expenseForm = useForm({
        description: `Obra/Reforma: ${work.title}`,
        amount: '',
        due_date: today,
        document_number: '',
        reminder_days: '3',
        notes: '',
    });

    const submitUpdate = (event: React.FormEvent) => {
        event.preventDefault();
        updateForm.post(route('works.updates.store', work.id), {
            preserveScroll: true,
            onSuccess: () => updateForm.reset('title', 'description', 'status'),
        });
    };

    const submitExpense = (event: React.FormEvent) => {
        event.preventDefault();
        expenseForm.post(route('works.expenses.store', work.id), {
            preserveScroll: true,
            onSuccess: () => expenseForm.reset('amount', 'document_number', 'notes'),
        });
    };

    const removeAttachment = (attachment: Attachment) => {
        if (confirm(`Remover o anexo "${attachment.original_filename ?? 'arquivo'}"?`)) {
            router.delete(route('attachments.destroy', attachment.id), { preserveScroll: true });
        }
    };

    const removeWork = () => {
        if (confirm(`Remover "${work.title}"?`)) {
            router.delete(route('works.destroy', work.id));
        }
    };

    return (
        <AppLayout>
            <Head title={work.title} />

            <div className="mx-auto max-w-6xl space-y-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <Link href={route('works.index')} className="text-sm text-gray-500 hover:text-gray-700">← Obras</Link>
                        <div className="mt-1 flex flex-wrap items-center gap-2">
                            <Hammer className="h-6 w-6 text-blue-600" />
                            <h1 className="text-2xl font-bold text-gray-900">{work.title}</h1>
                            <span className={`rounded-full px-2 py-1 text-xs font-medium ${statusClass[work.status] ?? 'bg-gray-100 text-gray-600'}`}>{work.status_label}</span>
                        </div>
                        <p className="mt-1 text-sm text-gray-500">
                            {work.condominium?.name ?? '-'} · {work.type_label} · {work.priority_label}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {can('works:update') && (
                            <Link href={route('works.edit', work.id)} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <Pencil className="h-4 w-4" /> Editar
                            </Link>
                        )}
                        {can('works:delete') && (
                            <button onClick={removeWork} className="inline-flex items-center gap-2 rounded-lg border border-red-100 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">
                                <Trash2 className="h-4 w-4" /> Remover
                            </button>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <Info label="Fornecedor" icon={UserRound} value={work.supplier ? (canOpenSuppliers ? <Link href={route('suppliers.show', work.supplier.id)} className="text-blue-600 hover:underline">{work.supplier.name}</Link> : work.supplier.name) : '-'} />
                    <Info label="Início" icon={CalendarDays} value={date(work.start_date)} />
                    <Info label="Previsão" icon={CalendarDays} value={date(work.expected_end_date)} />
                    <Info label="Orçamento" icon={Wallet} value={brl(work.budget_amount)} />
                    <Info label="Contas abertas" icon={Receipt} value={brl(openExpensesTotal)} />
                </div>

                <div className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div className="mb-2 flex items-center justify-between gap-3">
                        <p className="text-sm font-semibold uppercase text-gray-700">Progresso</p>
                        <span className="text-sm font-semibold text-gray-900">{work.progress_percent}%</span>
                    </div>
                    <div className="h-2.5 overflow-hidden rounded-full bg-gray-100">
                        <div className="h-full rounded-full bg-blue-600" style={{ width: `${Math.min(Math.max(work.progress_percent, 0), 100)}%` }} />
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <div className="space-y-4">
                        <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                            <h2 className="text-sm font-semibold uppercase text-gray-700">Escopo</h2>
                            {work.description ? <p className="mt-3 whitespace-pre-wrap text-sm text-gray-700">{work.description}</p> : <p className="mt-3 text-sm text-gray-400">Sem escopo informado.</p>}
                            {work.notes && (
                                <div className="mt-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-600">
                                    <p className="mb-1 text-xs font-semibold uppercase text-gray-500">Observações internas</p>
                                    <p className="whitespace-pre-wrap">{work.notes}</p>
                                </div>
                            )}
                        </div>

                        <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                            <h2 className="mb-4 flex items-center gap-2 text-sm font-semibold uppercase text-gray-700">
                                <Paperclip className="h-4 w-4" /> Anexos
                            </h2>
                            {work.attachments.length === 0 && <p className="text-sm text-gray-400">Nenhum anexo registrado.</p>}
                            <div className="space-y-2">
                                {work.attachments.map((attachment) => (
                                    <div key={attachment.id} className="flex items-center justify-between gap-3 rounded-lg border border-gray-100 px-3 py-2 text-sm">
                                        <a href={route('attachments.download', attachment.id)} className="inline-flex min-w-0 items-center gap-2 text-blue-600 hover:underline">
                                            <Paperclip className="h-4 w-4 flex-shrink-0" />
                                            <span className="truncate">{attachment.original_filename ?? 'arquivo'}</span>
                                        </a>
                                        <div className="flex flex-shrink-0 items-center gap-2 text-xs text-gray-400">
                                            <span>{size(attachment.file_size_bytes)}</span>
                                            {can('works:update') && (
                                                <button onClick={() => removeAttachment(attachment)} className="rounded p-1 hover:bg-red-50 hover:text-red-500">
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                            <h2 className="mb-4 flex items-center gap-2 text-sm font-semibold uppercase text-gray-700">
                                <Receipt className="h-4 w-4" /> Contas vinculadas
                            </h2>
                            <div className="mb-3 flex flex-wrap gap-4 text-sm text-gray-600">
                                <span>Total: <strong className="text-gray-900">{brl(expensesTotal)}</strong></span>
                                <span>Em aberto: <strong className="text-gray-900">{brl(openExpensesTotal)}</strong></span>
                            </div>
                            <div className="overflow-hidden rounded-lg border border-gray-100">
                                <table className="w-full text-sm">
                                    <thead className="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                                        <tr>
                                            <th className="px-3 py-2">Conta</th>
                                            <th className="px-3 py-2">Vencimento</th>
                                            <th className="px-3 py-2 text-right">Valor</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-50">
                                        {work.expenses.length === 0 && <tr><td colSpan={3} className="px-3 py-6 text-center text-gray-400">Nenhuma conta vinculada.</td></tr>}
                                        {work.expenses.map((expense) => (
                                            <tr key={expense.id}>
                                                <td className="px-3 py-2">
                                                    {canOpenExpenses ? (
                                                        <Link href={canEditExpenses ? route('expenses.edit', expense.id) : route('expenses.index')} className="font-medium text-blue-600 hover:underline">{expense.description}</Link>
                                                    ) : (
                                                        <span className="font-medium text-gray-900">{expense.description}</span>
                                                    )}
                                                    <p className="text-xs text-gray-400">{expense.display_status_label}</p>
                                                </td>
                                                <td className="px-3 py-2 text-xs text-gray-600">{date(expense.due_date)}</td>
                                                <td className="px-3 py-2 text-right font-medium text-gray-900">{brl(expense.amount)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-4">
                        <div className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                            <h2 className="mb-3 flex items-center gap-2 text-sm font-semibold uppercase text-gray-700">
                                <ClipboardCheck className="h-4 w-4" /> Origem
                            </h2>
                            <div className="space-y-2 text-sm text-gray-600">
                                <p>Criada por: <span className="font-medium text-gray-900">{work.creator?.name ?? '-'}</span></p>
                                <p>Responsável: <span className="font-medium text-gray-900">{work.responsible_name ?? '-'}</span></p>
                                {work.quotation && (
                                    <p>
                                        Orçamento:{' '}
                                        {canOpenQuotations ? <Link href={route('quotations.show', work.quotation.id)} className="font-medium text-blue-600 hover:underline">{work.quotation.title}</Link> : <span className="font-medium text-gray-900">{work.quotation.title}</span>}
                                    </p>
                                )}
                                {work.quotation_proposal && <p>Proposta: <span className="font-medium text-gray-900">{work.quotation_proposal.supplier_name} · {brl(work.quotation_proposal.amount)}</span></p>}
                                <p>Custo final: <span className="font-medium text-gray-900">{brl(work.final_amount)}</span></p>
                                {work.budget_variance !== null && <p>Variação: <span className={work.budget_variance > 0 ? 'font-medium text-red-600' : 'font-medium text-emerald-600'}>{brl(work.budget_variance)}</span></p>}
                            </div>
                        </div>

                        {can('works:update') && (
                            <form onSubmit={submitUpdate} className="space-y-3 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                                <h2 className="flex items-center gap-2 text-sm font-semibold uppercase text-gray-700">
                                    <Plus className="h-4 w-4" /> Novo andamento
                                </h2>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-600">Título *</label>
                                    <input value={updateForm.data.title} onChange={(e) => updateForm.setData('title', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                                    {updateForm.errors.title && <p className="mt-1 text-xs text-red-600">{updateForm.errors.title}</p>}
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-gray-600">Status</label>
                                        <select value={updateForm.data.status} onChange={(e) => updateForm.setData('status', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                                            <option value="">Não alterar</option>
                                            {Object.entries(statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-gray-600">Progresso</label>
                                        <input type="number" min="0" max="100" value={updateForm.data.progress_percent} onChange={(e) => updateForm.setData('progress_percent', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                                    </div>
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-600">Data</label>
                                    <input type="date" value={updateForm.data.occurred_at} onChange={(e) => updateForm.setData('occurred_at', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-600">Descrição</label>
                                    <textarea value={updateForm.data.description} onChange={(e) => updateForm.setData('description', e.target.value)} rows={3} className="w-full resize-none rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                                </div>
                                <button type="submit" disabled={updateForm.processing} className="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                                    {updateForm.processing ? 'Salvando...' : 'Registrar andamento'}
                                </button>
                            </form>
                        )}

                        {canGenerateExpense && (
                            <form onSubmit={submitExpense} className="space-y-3 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                                <h2 className="flex items-center gap-2 text-sm font-semibold uppercase text-gray-700">
                                    <Receipt className="h-4 w-4" /> Nova conta vinculada
                                </h2>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-600">Descrição</label>
                                    <input value={expenseForm.data.description} onChange={(e) => expenseForm.setData('description', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-gray-600">Valor *</label>
                                        <input type="number" min="0" step="0.01" value={expenseForm.data.amount} onChange={(e) => expenseForm.setData('amount', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                                        {expenseForm.errors.amount && <p className="mt-1 text-xs text-red-600">{expenseForm.errors.amount}</p>}
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-gray-600">Vencimento *</label>
                                        <input type="date" value={expenseForm.data.due_date} onChange={(e) => expenseForm.setData('due_date', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                                        {expenseForm.errors.due_date && <p className="mt-1 text-xs text-red-600">{expenseForm.errors.due_date}</p>}
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-gray-600">Nº documento</label>
                                        <input value={expenseForm.data.document_number} onChange={(e) => expenseForm.setData('document_number', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-medium text-gray-600">Lembrar com</label>
                                        <input type="number" min="0" max="60" value={expenseForm.data.reminder_days} onChange={(e) => expenseForm.setData('reminder_days', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                                    </div>
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-gray-600">Observações</label>
                                    <textarea value={expenseForm.data.notes} onChange={(e) => expenseForm.setData('notes', e.target.value)} rows={2} className="w-full resize-none rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                                </div>
                                <button type="submit" disabled={expenseForm.processing} className="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                                    {expenseForm.processing ? 'Salvando...' : 'Gerar conta'}
                                </button>
                            </form>
                        )}
                    </div>
                </div>

                <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 className="mb-4 text-sm font-semibold uppercase text-gray-700">Linha do tempo</h2>
                    {work.updates.length === 0 && <p className="text-sm text-gray-400">Nenhum andamento registrado.</p>}
                    <div className="space-y-4">
                        {work.updates.map((update) => (
                            <div key={update.id} className="border-l-2 border-blue-100 pl-4">
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="font-medium text-gray-900">{update.title}</p>
                                    {update.status_label && <span className="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">{update.status_label}</span>}
                                    {update.progress_percent !== null && <span className="text-xs text-gray-400">{update.progress_percent}%</span>}
                                </div>
                                <p className="mt-1 text-xs text-gray-400">{date(update.occurred_at)} · {update.author?.name ?? '-'}</p>
                                {update.description && <p className="mt-2 whitespace-pre-wrap text-sm text-gray-600">{update.description}</p>}
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
