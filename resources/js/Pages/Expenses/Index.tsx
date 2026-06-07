import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    Download,
    Pencil,
    Plus,
    Receipt,
    Trash2,
    Wallet,
} from 'lucide-react';
import type { PageProps } from '@/types';

interface Option { value: string; label: string }

interface Expense {
    id: string;
    category: string;
    status: string;
    display_status: string;
    display_status_label: string;
    description: string;
    amount: string;
    due_date: string | null;
    paid_at: string | null;
    supplier: string | null;
    document_number: string | null;
    receipt_storage_object_id: string | null;
    days_until_due: number | null;
    condominium: { name: string } | null;
    supplier_record: { name: string } | null;
    maintenance_record: { plan: { id: string; title: string } | null } | null;
    quotation_proposal: { quotation: { id: string; title: string } | null } | null;
}

interface Summary {
    open_total: number;
    overdue_total: number;
    due_next_7_days: number;
    paid_this_month: number;
}

interface Props {
    expenses: {
        data: Expense[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    total: string | number;
    summary: Summary;
    condominiums: Option[];
    suppliers: Option[];
    categories: Record<string, string>;
    statuses: Record<string, string>;
    filters: {
        condominium_id?: string;
        category?: string;
        supplier_id?: string;
        status?: string;
        from?: string;
        to?: string;
    };
}

const brl = (value: string | number) => Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const formatDate = (value: string | null) => {
    if (!value) return '-';
    return new Date(value.slice(0, 10) + 'T00:00:00').toLocaleDateString('pt-BR');
};

const statusClass: Record<string, string> = {
    pending: 'bg-blue-50 text-blue-700',
    overdue: 'bg-red-50 text-red-700',
    paid: 'bg-emerald-50 text-emerald-700',
    cancelled: 'bg-gray-100 text-gray-500',
};

function SummaryCard({ label, value, icon: Icon, tone }: { label: string; value: number; icon: React.ElementType; tone: string }) {
    return (
        <div className="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-gray-500">{label}</p>
                    <p className="mt-1 text-lg font-bold text-gray-900">{brl(value)}</p>
                </div>
                <div className={`rounded-lg p-2 ${tone}`}>
                    <Icon className="h-5 w-5 text-white" />
                </div>
            </div>
        </div>
    );
}

export default function ExpensesIndex({ expenses, total, summary, condominiums, suppliers, categories, statuses, filters }: Props) {
    const { auth, tenant } = usePage<PageProps>().props;
    const perms = auth.user?.permissions ?? [];
    const can = (permission: string) => perms.includes('*') || perms.includes(permission);
    const planAllowsMaintenance = auth.user?.is_super_admin || (tenant?.plan?.modules ?? []).includes('maintenance');
    const planAllowsQuotations = auth.user?.is_super_admin || (tenant?.plan?.modules ?? []).includes('quotations');
    const canOpenMaintenance = can('maintenance:read') && planAllowsMaintenance;
    const canOpenQuotations = can('quotations:read') && planAllowsQuotations;

    const apply = (params: Record<string, string>) => router.get(
        route('expenses.index'),
        { ...filters, ...params },
        { preserveState: true, replace: true },
    );

    const clearFilters = () => router.get(route('expenses.index'), {}, { replace: true });

    const markPaid = (expense: Expense) => {
        if (confirm(`Marcar "${expense.description}" como paga?`)) {
            router.post(route('expenses.pay', expense.id), {}, { preserveScroll: true });
        }
    };

    const remove = (expense: Expense) => {
        if (confirm(`Remover "${expense.description}"?`)) {
            router.delete(route('expenses.destroy', expense.id));
        }
    };

    return (
        <AppLayout>
            <Head title="Contas a pagar" />

            <div className="space-y-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Contas a pagar</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Total no filtro: <span className="font-semibold text-gray-700">{brl(total)}</span>
                        </p>
                    </div>
                    {can('expenses:create') && (
                        <Link href={route('expenses.create')} className="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                            <Plus className="h-4 w-4" /> Nova conta
                        </Link>
                    )}
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard label="Em aberto" value={summary.open_total} icon={Wallet} tone="bg-blue-600" />
                    <SummaryCard label="Vencidas" value={summary.overdue_total} icon={AlertTriangle} tone="bg-red-600" />
                    <SummaryCard label="Vencem em 7 dias" value={summary.due_next_7_days} icon={Clock} tone="bg-amber-500" />
                    <SummaryCard label="Pagas no mês" value={summary.paid_this_month} icon={CheckCircle2} tone="bg-emerald-600" />
                </div>

                <div className="flex flex-col gap-2 rounded-lg border border-gray-100 bg-white p-3 shadow-sm lg:flex-row lg:flex-wrap">
                    <select value={filters.condominium_id ?? ''} onChange={(e) => apply({ condominium_id: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="">Todos os condomínios</option>
                        {condominiums.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                    </select>
                    <select value={filters.status ?? ''} onChange={(e) => apply({ status: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="">Todos os status</option>
                        {Object.entries(statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                    <select value={filters.category ?? ''} onChange={(e) => apply({ category: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="">Todas as categorias</option>
                        {Object.entries(categories).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                    <select value={filters.supplier_id ?? ''} onChange={(e) => apply({ supplier_id: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="">Todos os fornecedores</option>
                        {suppliers.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                    </select>
                    <input type="date" value={filters.from ?? ''} onChange={(e) => apply({ from: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                    <input type="date" value={filters.to ?? ''} onChange={(e) => apply({ to: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                    <button onClick={clearFilters} className="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                        Limpar
                    </button>
                </div>

                <div className="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b border-gray-100 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Conta</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Vencimento</th>
                                <th className="px-4 py-3 text-right">Valor</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {expenses.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-4 py-10 text-center text-gray-400">
                                        <Receipt className="mx-auto mb-2 h-8 w-8 text-gray-300" />
                                        Nenhuma conta encontrada.
                                    </td>
                                </tr>
                            )}
                            {expenses.data.map((expense) => {
                                const supplierName = expense.supplier_record?.name ?? expense.supplier;
                                const canPay = can('expenses:update') && !['paid', 'cancelled'].includes(expense.display_status);

                                return (
                                    <tr key={expense.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            <p className="font-medium text-gray-900">{expense.description}</p>
                                            <p className="text-xs text-gray-500">
                                                {expense.condominium?.name ?? '-'}
                                                {supplierName ? ` · ${supplierName}` : ''}
                                                {expense.document_number ? ` · Doc. ${expense.document_number}` : ''}
                                            </p>
                                            {expense.maintenance_record?.plan && (
                                                <p className="mt-1 text-xs text-gray-400">
                                                    Origem:{' '}
                                                    {canOpenMaintenance ? (
                                                        <Link href={route('maintenance.show', expense.maintenance_record.plan.id)} className="text-blue-600 hover:underline">
                                                            {expense.maintenance_record.plan.title}
                                                        </Link>
                                                    ) : expense.maintenance_record.plan.title}
                                                </p>
                                            )}
                                            {expense.quotation_proposal?.quotation && (
                                                <p className="mt-1 text-xs text-gray-400">
                                                    Origem:{' '}
                                                    {canOpenQuotations ? (
                                                        <Link href={route('quotations.show', expense.quotation_proposal.quotation.id)} className="text-blue-600 hover:underline">
                                                            {expense.quotation_proposal.quotation.title}
                                                        </Link>
                                                    ) : expense.quotation_proposal.quotation.title}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-flex rounded-full px-2 py-1 text-xs font-medium ${statusClass[expense.display_status] ?? 'bg-gray-100 text-gray-600'}`}>
                                                {expense.display_status_label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-gray-600">
                                            {formatDate(expense.due_date)}
                                            {expense.display_status === 'pending' && expense.days_until_due !== null && (
                                                <span className="block text-xs text-gray-400">
                                                    {expense.days_until_due === 0 ? 'vence hoje' : `em ${expense.days_until_due} dia(s)`}
                                                </span>
                                            )}
                                            {expense.display_status === 'overdue' && expense.days_until_due !== null && (
                                                <span className="block text-xs text-red-500">
                                                    atrasada há {Math.abs(expense.days_until_due)} dia(s)
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right font-medium text-gray-900">{brl(expense.amount)}</td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                {canPay && (
                                                    <button onClick={() => markPaid(expense)} className="rounded p-1 text-gray-400 hover:text-emerald-600" title="Marcar como paga">
                                                        <CheckCircle2 className="h-4 w-4" />
                                                    </button>
                                                )}
                                                {expense.receipt_storage_object_id && (
                                                    <a href={route('expenses.download', expense.id)} className="rounded p-1 text-gray-400 hover:text-blue-600" title="Nota fiscal ou comprovante">
                                                        <Download className="h-4 w-4" />
                                                    </a>
                                                )}
                                                {can('expenses:update') && (
                                                    <Link href={route('expenses.edit', expense.id)} className="rounded p-1 text-gray-400 hover:text-blue-600" title="Editar">
                                                        <Pencil className="h-4 w-4" />
                                                    </Link>
                                                )}
                                                {can('expenses:delete') && (
                                                    <button onClick={() => remove(expense)} className="rounded p-1 text-gray-400 hover:text-red-500" title="Remover">
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {expenses.links.length > 3 && (
                    <div className="flex flex-wrap gap-1">
                        {expenses.links.map((link, index) => (
                            <button
                                key={index}
                                disabled={!link.url}
                                onClick={() => link.url && router.visit(link.url)}
                                className={`rounded px-3 py-1.5 text-sm ${link.active ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'} disabled:opacity-40`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
