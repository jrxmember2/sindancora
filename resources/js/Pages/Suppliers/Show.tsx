import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CalendarCheck, Globe, Mail, MapPin, Pencil, Phone, Receipt, Star, Trash2, Truck, Wrench } from 'lucide-react';
import { maskCpfCnpj } from '@/lib/masks';
import type { PageProps } from '@/types';

interface Evaluation {
    id: string;
    score: number;
    comment: string | null;
    created_at: string;
    author: { id: string; name: string } | null;
}

interface Supplier {
    id: string;
    name: string;
    category: string | null;
    document: string | null;
    contact_name: string | null;
    phone: string | null;
    email: string | null;
    website: string | null;
    zip_code: string | null;
    street: string | null;
    number: string | null;
    complement: string | null;
    neighborhood: string | null;
    city: string | null;
    state: string | null;
    notes: string | null;
    is_active: boolean;
    condominiums: { id: string; name: string }[];
    evaluations: Evaluation[];
    evaluations_count: number;
    evaluations_avg_score: number | null;
}

interface SupplierStats {
    active_maintenance_plans: number;
    maintenance_records: number;
    open_expenses_total: number;
    overdue_expenses_total: number;
    paid_this_month: number;
}

interface MaintenancePlan {
    id: string;
    title: string;
    category: string | null;
    frequency: string;
    next_due_date: string | null;
    status: string | null;
    days_until_due: number | null;
    is_active: boolean;
    condominium: { id: string; name: string } | null;
}

interface MaintenanceRecord {
    id: string;
    done_date: string;
    cost: string | null;
    notes: string | null;
    plan: { id: string; title: string; condominium: { id: string; name: string } | null } | null;
    expense: { id: string; status: string; due_date: string | null; amount: string; description: string } | null;
}

interface Expense {
    id: string;
    description: string;
    amount: string;
    status: string;
    display_status: string;
    display_status_label: string;
    due_date: string | null;
    document_number: string | null;
    condominium: { id: string; name: string } | null;
    maintenance_record: { plan: { id: string; title: string } | null } | null;
}

interface Props {
    supplier: Supplier;
    categories: Record<string, string>;
    maintenanceCategories: Record<string, string>;
    supplierStats: SupplierStats;
    maintenancePlans: MaintenancePlan[];
    maintenanceRecords: MaintenanceRecord[];
    expenses: Expense[];
}

const expenseStatus: Record<string, string> = {
    pending: 'Pendente',
    paid: 'Paga',
    overdue: 'Vencida',
    cancelled: 'Cancelada',
};

function Stars({ value, className = 'h-4 w-4' }: { value: number; className?: string }) {
    return (
        <span className="inline-flex items-center">
            {[1, 2, 3, 4, 5].map(i => (
                <Star key={i} className={`${className} ${i <= value ? 'fill-amber-400 text-amber-400' : 'text-gray-300'}`} />
            ))}
        </span>
    );
}

function fmtDate(value: string | null) {
    if (!value) return '-';

    return new Date(value.slice(0, 10) + 'T00:00:00').toLocaleDateString('pt-BR');
}

function fmtMoney(value: string | number | null) {
    return Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function StatCard({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
            <p className="text-xs font-medium uppercase tracking-wide text-gray-500">{label}</p>
            <p className="mt-1 text-lg font-bold text-gray-900">{value}</p>
        </div>
    );
}

export default function SupplierShow({ supplier, categories, maintenanceCategories, supplierStats, maintenancePlans, maintenanceRecords, expenses }: Props) {
    const { auth, tenant } = usePage<PageProps>().props;
    const permissions = auth.user?.permissions ?? [];
    const can = (permission: string) => permissions.includes('*') || permissions.includes(permission);
    const planAllows = (module: string) => auth.user?.is_super_admin || (tenant?.plan?.modules ?? []).includes(module);
    const canOpenMaintenance = can('maintenance:read') && planAllows('maintenance');
    const canOpenExpenses = can('expenses:read') && planAllows('financial');
    const canEditExpenses = can('expenses:update') && planAllows('financial');

    const form = useForm({ score: 5, comment: '' });

    const submitEvaluation = () => form.post(route('suppliers.evaluations.store', supplier.id), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });

    const removeEvaluation = (id: string) => {
        if (confirm('Remover esta avaliação?')) {
            router.delete(route('suppliers.evaluations.destroy', id), { preserveScroll: true });
        }
    };

    const address = [supplier.street, supplier.number, supplier.neighborhood, supplier.city, supplier.state]
        .filter(Boolean)
        .join(', ');

    return (
        <AppLayout>
            <Head title={supplier.name} />
            <div className="mx-auto max-w-5xl space-y-6">
                <div className="flex items-start justify-between">
                    <div>
                        <Link href={route('suppliers.index')} className="text-sm text-gray-500 hover:text-gray-700">← Fornecedores</Link>
                        <div className="mt-1 flex items-center gap-2">
                            <Truck className="h-6 w-6 text-blue-600" />
                            <h1 className="text-2xl font-bold text-gray-900">{supplier.name}</h1>
                            {!supplier.is_active && <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">inativo</span>}
                        </div>
                    </div>
                    {can('suppliers:update') && (
                        <Link href={route('suppliers.edit', supplier.id)} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                            <Pencil className="h-4 w-4" /> Editar
                        </Link>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <p className="text-xs uppercase tracking-wide text-gray-500">Categoria</p>
                        <p className="mt-1 text-sm font-medium text-gray-900">{supplier.category ? (categories[supplier.category] ?? supplier.category) : '-'}</p>
                    </div>
                    <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <p className="text-xs uppercase tracking-wide text-gray-500">CPF / CNPJ</p>
                        <p className="mt-1 text-sm font-medium text-gray-900">{supplier.document ? maskCpfCnpj(supplier.document) : '-'}</p>
                    </div>
                    <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <p className="text-xs uppercase tracking-wide text-gray-500">Avaliação média</p>
                        <div className="mt-1 flex items-center gap-2">
                            {supplier.evaluations_count && supplier.evaluations_avg_score !== null ? (
                                <>
                                    <Stars value={Math.round(supplier.evaluations_avg_score)} />
                                    <span className="text-sm text-gray-600">{Number(supplier.evaluations_avg_score).toFixed(1)} ({supplier.evaluations_count})</span>
                                </>
                            ) : <span className="text-sm text-gray-400">sem avaliação</span>}
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <StatCard label="Manutenções ativas" value={supplierStats.active_maintenance_plans} />
                    <StatCard label="Execuções" value={supplierStats.maintenance_records} />
                    <StatCard label="Em aberto" value={fmtMoney(supplierStats.open_expenses_total)} />
                    <StatCard label="Vencidas" value={fmtMoney(supplierStats.overdue_expenses_total)} />
                    <StatCard label="Pago no mês" value={fmtMoney(supplierStats.paid_this_month)} />
                </div>

                <div className="space-y-3 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-700">Contato</h2>
                    <div className="grid gap-2 text-sm text-gray-700 sm:grid-cols-2">
                        {supplier.contact_name && <p>{supplier.contact_name}</p>}
                        {supplier.phone && <p className="flex items-center gap-2"><Phone className="h-4 w-4 text-gray-400" /> {supplier.phone}</p>}
                        {supplier.email && <p className="flex items-center gap-2"><Mail className="h-4 w-4 text-gray-400" /> {supplier.email}</p>}
                        {supplier.website && <p className="flex items-center gap-2"><Globe className="h-4 w-4 text-gray-400" /> <a href={supplier.website} target="_blank" rel="noreferrer" className="text-blue-600 hover:underline">{supplier.website}</a></p>}
                        {address && <p className="flex items-center gap-2"><MapPin className="h-4 w-4 text-gray-400" /> {address}</p>}
                    </div>
                    {supplier.condominiums.length > 0 && (
                        <div className="pt-2">
                            <p className="text-xs uppercase tracking-wide text-gray-500">Condomínios atendidos</p>
                            <div className="mt-1 flex flex-wrap gap-1">
                                {supplier.condominiums.map(condominium => <span key={condominium.id} className="rounded-full bg-blue-50 px-2 py-0.5 text-xs text-blue-700">{condominium.name}</span>)}
                            </div>
                        </div>
                    )}
                    {supplier.notes && <p className="border-t border-gray-100 pt-3 text-sm text-gray-600">{supplier.notes}</p>}
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <div className="space-y-4 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h2 className="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                            <Wrench className="h-4 w-4" /> Manutenções vinculadas
                        </h2>
                        <div className="divide-y divide-gray-50">
                            {maintenancePlans.length === 0 && <p className="py-4 text-sm text-gray-500">Nenhuma manutenção vinculada.</p>}
                            {maintenancePlans.map(plan => (
                                <div key={plan.id} className="py-3">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            {canOpenMaintenance ? (
                                                <Link href={route('maintenance.show', plan.id)} className="font-medium text-gray-900 hover:text-blue-600">{plan.title}</Link>
                                            ) : (
                                                <p className="font-medium text-gray-900">{plan.title}</p>
                                            )}
                                            <p className="mt-0.5 text-xs text-gray-500">
                                                {plan.condominium?.name ?? '-'} · {plan.category ? (maintenanceCategories[plan.category] ?? plan.category) : '-'}
                                            </p>
                                        </div>
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${plan.status === 'overdue' ? 'bg-red-50 text-red-700' : plan.status === 'due_soon' ? 'bg-amber-50 text-amber-700' : 'bg-green-50 text-green-700'}`}>
                                            {fmtDate(plan.next_due_date)}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="space-y-4 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h2 className="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                            <Receipt className="h-4 w-4" /> Contas a pagar
                        </h2>
                        <div className="divide-y divide-gray-50">
                            {expenses.length === 0 && <p className="py-4 text-sm text-gray-500">Nenhuma conta vinculada.</p>}
                            {expenses.map(expense => {
                                const href = canEditExpenses ? route('expenses.edit', expense.id) : route('expenses.index');

                                return (
                                    <div key={expense.id} className="flex items-start justify-between gap-3 py-3">
                                        <div>
                                            {canOpenExpenses ? (
                                                <Link href={href} className="font-medium text-gray-900 hover:text-blue-600">{expense.description}</Link>
                                            ) : (
                                                <p className="font-medium text-gray-900">{expense.description}</p>
                                            )}
                                            <p className="mt-0.5 text-xs text-gray-500">
                                                {expense.condominium?.name ?? '-'} · venc. {fmtDate(expense.due_date)}
                                                {expense.document_number ? ` · Doc. ${expense.document_number}` : ''}
                                            </p>
                                            {expense.maintenance_record?.plan && (
                                                <p className="mt-1 text-xs text-gray-400">
                                                    Origem: {expense.maintenance_record.plan.title}
                                                </p>
                                            )}
                                        </div>
                                        <div className="text-right">
                                            <p className="text-sm font-semibold text-gray-900">{fmtMoney(expense.amount)}</p>
                                            <p className="text-xs text-gray-500">{expense.display_status_label}</p>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>

                <div className="space-y-4 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 className="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                        <CalendarCheck className="h-4 w-4" /> Execuções recentes
                    </h2>
                    <div className="overflow-hidden rounded-lg border border-gray-100">
                        <table className="w-full text-sm">
                            <thead className="border-b border-gray-100 bg-gray-50">
                                <tr>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Data</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Manutenção</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Custo</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Conta</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {maintenanceRecords.length === 0 && (
                                    <tr><td colSpan={4} className="px-3 py-6 text-center text-sm text-gray-500">Nenhuma execução registrada.</td></tr>
                                )}
                                {maintenanceRecords.map(record => (
                                    <tr key={record.id}>
                                        <td className="px-3 py-2 text-gray-700">{fmtDate(record.done_date)}</td>
                                        <td className="px-3 py-2 text-xs text-gray-600">
                                            {record.plan && canOpenMaintenance ? (
                                                <Link href={route('maintenance.show', record.plan.id)} className="text-blue-600 hover:underline">{record.plan.title}</Link>
                                            ) : record.plan?.title ?? '-'}
                                            {record.plan?.condominium && <p className="text-gray-400">{record.plan.condominium.name}</p>}
                                        </td>
                                        <td className="px-3 py-2 text-xs text-gray-600">{record.cost ? fmtMoney(record.cost) : '-'}</td>
                                        <td className="px-3 py-2 text-xs text-gray-600">
                                            {record.expense ? (
                                                canOpenExpenses ? (
                                                    <Link href={canEditExpenses ? route('expenses.edit', record.expense.id) : route('expenses.index')} className="text-blue-600 hover:underline">
                                                        {fmtMoney(record.expense.amount)} · {expenseStatus[record.expense.status] ?? record.expense.status}
                                                    </Link>
                                                ) : (
                                                    <span>{fmtMoney(record.expense.amount)} · {expenseStatus[record.expense.status] ?? record.expense.status}</span>
                                                )
                                            ) : '-'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="space-y-4 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-700">Avaliações</h2>

                    {can('suppliers:update') && (
                        <div className="space-y-3 rounded-lg bg-gray-50 p-4">
                            <div className="flex items-center gap-3">
                                <span className="text-sm text-gray-600">Sua nota:</span>
                                {[1, 2, 3, 4, 5].map(i => (
                                    <button key={i} type="button" onClick={() => form.setData('score', i)}>
                                        <Star className={`h-6 w-6 ${i <= form.data.score ? 'fill-amber-400 text-amber-400' : 'text-gray-300'}`} />
                                    </button>
                                ))}
                            </div>
                            <textarea value={form.data.comment} onChange={event => form.setData('comment', event.target.value)} rows={2} placeholder="Comentário (opcional)..." className="w-full resize-none rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                            <button onClick={submitEvaluation} disabled={form.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50">
                                {form.processing ? 'Salvando...' : 'Avaliar'}
                            </button>
                        </div>
                    )}

                    <div className="divide-y divide-gray-50">
                        {supplier.evaluations.length === 0 && <p className="py-4 text-sm text-gray-500">Nenhuma avaliação ainda.</p>}
                        {supplier.evaluations.map(evaluation => (
                            <div key={evaluation.id} className="flex items-start justify-between py-3">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <Stars value={evaluation.score} className="h-3.5 w-3.5" />
                                        <span className="text-xs text-gray-500">{evaluation.author?.name ?? 'Usuário'} · {fmtDate(evaluation.created_at)}</span>
                                    </div>
                                    {evaluation.comment && <p className="mt-1 text-sm text-gray-700">{evaluation.comment}</p>}
                                </div>
                                {can('suppliers:delete') && (
                                    <button onClick={() => removeEvaluation(evaluation.id)} className="rounded p-1.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-500"><Trash2 className="h-4 w-4" /></button>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
