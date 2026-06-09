import AppLayout from '@/Layouts/AppLayout';
import { maskCpfCnpj } from '@/lib/masks';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, BriefcaseBusiness, Pencil, Plus, Trash2 } from 'lucide-react';
import type { PageProps } from '@/types';

interface Option { value: string; label: string }

interface VacationPeriod {
    id: string;
    acquisition_start: string | null;
    acquisition_end: string | null;
    deadline_date: string | null;
    vacation_start: string | null;
    vacation_end: string | null;
    days: number;
    status: string;
    status_label: string;
    days_until_deadline: number | null;
    deadline_status: string | null;
}

interface Employee {
    id: string;
    name: string;
    document: string | null;
    email: string | null;
    phone: string | null;
    position: string | null;
    employment_type: string;
    employment_type_label: string;
    status: string;
    status_label: string;
    admission_date: string | null;
    condominium: { id: string; name: string } | null;
    open_vacation_periods: VacationPeriod[];
}

interface Props {
    employees: { data: Employee[]; links: { url: string | null; label: string; active: boolean }[] };
    summary: { total: number; active: number; due_soon: number; overdue: number };
    condominiums: Option[];
    statuses: Record<string, string>;
    employmentTypes: Record<string, string>;
    filters: { search?: string; condominium_id?: string | null; status?: string; vacation_status?: string };
}

const fmtDate = (value: string | null) => value ? new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString('pt-BR') : '-';

function SummaryCard({ label, value, tone }: { label: string; value: number; tone: string }) {
    return (
        <div className="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
            <p className="text-xs font-medium uppercase tracking-wide text-gray-500">{label}</p>
            <p className={`mt-1 text-2xl font-bold ${tone}`}>{value}</p>
        </div>
    );
}

function EmployeeStatus({ label, status }: { label: string; status: string }) {
    const cls = status === 'active'
        ? 'bg-green-50 text-green-700'
        : status === 'terminated'
            ? 'bg-gray-100 text-gray-600'
            : 'bg-amber-50 text-amber-700';

    return <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${cls}`}>{label}</span>;
}

function VacationStatus({ period }: { period?: VacationPeriod }) {
    if (!period) {
        return <span className="text-xs text-gray-400">Sem periodo aberto</span>;
    }

    if (period.deadline_status === 'overdue') {
        return <span className="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">Atrasada {period.days_until_deadline !== null ? `${Math.abs(period.days_until_deadline)}d` : ''}</span>;
    }

    if (period.deadline_status === 'due_soon') {
        return <span className="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">{period.days_until_deadline === 0 ? 'Vence hoje' : `Vence em ${period.days_until_deadline}d`}</span>;
    }

    return <span className="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Em dia</span>;
}

export default function EmployeesIndex({ employees, summary, condominiums, statuses, employmentTypes, filters }: Props) {
    const { auth } = usePage<PageProps>().props;
    const permissions = auth.user?.permissions ?? [];
    const can = (permission: string) => permissions.includes('*') || permissions.includes(permission);

    const apply = (extra: Record<string, string> = {}) => router.get(
        route('employees.index'),
        {
            search: filters.search ?? '',
            condominium_id: filters.condominium_id ?? '',
            status: filters.status ?? '',
            vacation_status: filters.vacation_status ?? '',
            ...extra,
        },
        { preserveState: true, replace: true },
    );

    const destroy = (id: string, name: string) => {
        if (confirm(`Remover o funcionario "${name}"?`)) {
            router.delete(route('employees.destroy', id));
        }
    };

    return (
        <AppLayout>
            <Head title="Funcionarios" />
            <div className="space-y-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2">
                        <BriefcaseBusiness className="h-6 w-6 text-blue-600" />
                        <h1 className="text-2xl font-bold text-gray-900">Funcionarios</h1>
                    </div>

                    {can('employees:create') && (
                        <Link href={route('employees.create')} className="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700">
                            <Plus className="h-4 w-4" /> Novo funcionario
                        </Link>
                    )}
                </div>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard label="Total" value={summary.total} tone="text-gray-900" />
                    <SummaryCard label="Ativos" value={summary.active} tone="text-green-700" />
                    <SummaryCard label="Ferias proximas" value={summary.due_soon} tone="text-amber-600" />
                    <SummaryCard label="Ferias atrasadas" value={summary.overdue} tone="text-red-600" />
                </div>

                {summary.overdue > 0 && (
                    <div className="flex items-center gap-2 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <AlertTriangle className="h-4 w-4" />
                        {summary.overdue} funcionario(s) com ferias atrasadas.
                    </div>
                )}

                <div className="flex flex-wrap gap-2">
                    <input
                        defaultValue={filters.search ?? ''}
                        onKeyDown={(event) => { if (event.key === 'Enter') apply({ search: (event.target as HTMLInputElement).value }); }}
                        placeholder="Buscar por nome, cargo ou documento..."
                        className="w-72 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                    />

                    <select value={filters.status ?? ''} onChange={(event) => apply({ status: event.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">Todos os status</option>
                        {Object.entries(statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>

                    <select value={filters.vacation_status ?? ''} onChange={(event) => apply({ vacation_status: event.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="">Todas as ferias</option>
                        <option value="due_soon">Proximas</option>
                        <option value="overdue">Atrasadas</option>
                    </select>

                    {condominiums.length > 1 && (
                        <select value={filters.condominium_id ?? ''} onChange={(event) => apply({ condominium_id: event.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                            <option value="">Todos os condominios</option>
                            {condominiums.map((condominium) => <option key={condominium.value} value={condominium.value}>{condominium.label}</option>)}
                        </select>
                    )}

                    <button onClick={() => router.get(route('employees.index'), {}, { replace: true })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                        Limpar
                    </button>
                </div>

                <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b border-gray-100 bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Funcionario</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Condominio</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Vinculo</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Admissao</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Ferias</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {employees.data.length === 0 && (
                                    <tr><td colSpan={7} className="px-4 py-8 text-center text-sm text-gray-500">Nenhum funcionario cadastrado.</td></tr>
                                )}

                                {employees.data.map((employee) => {
                                    const period = employee.open_vacation_periods[0];

                                    return (
                                        <tr key={employee.id} className="transition-colors hover:bg-gray-50">
                                            <td className="px-4 py-3">
                                                <Link href={route('employees.show', employee.id)} className="font-medium text-gray-900 hover:text-blue-600">{employee.name}</Link>
                                                <p className="text-xs text-gray-400">
                                                    {employee.position || '-'}
                                                    {employee.document ? ` | ${maskCpfCnpj(employee.document)}` : ''}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3 text-xs text-gray-600">{employee.condominium?.name ?? '-'}</td>
                                            <td className="px-4 py-3 text-xs text-gray-600">{employmentTypes[employee.employment_type] ?? employee.employment_type_label}</td>
                                            <td className="px-4 py-3 text-xs text-gray-600">{fmtDate(employee.admission_date)}</td>
                                            <td className="px-4 py-3">
                                                <VacationStatus period={period} />
                                                {period && <p className="mt-1 text-xs text-gray-400">Limite {fmtDate(period.deadline_date)}</p>}
                                            </td>
                                            <td className="px-4 py-3"><EmployeeStatus label={employee.status_label} status={employee.status} /></td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center justify-end gap-1">
                                                    {can('employees:update') && (
                                                        <Link href={route('employees.edit', employee.id)} className="rounded p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600">
                                                            <Pencil className="h-4 w-4" />
                                                        </Link>
                                                    )}
                                                    {can('employees:delete') && (
                                                        <button onClick={() => destroy(employee.id, employee.name)} className="rounded p-1.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-500">
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
                </div>

                {employees.links.length > 3 && (
                    <div className="flex flex-wrap gap-1">
                        {employees.links.map((link, index) => (
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
