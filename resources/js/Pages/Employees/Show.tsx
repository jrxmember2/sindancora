import AppLayout from '@/Layouts/AppLayout';
import { maskCpfCnpj, maskPhone } from '@/lib/masks';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { BriefcaseBusiness, CalendarDays, Pencil, Plus, Trash2 } from 'lucide-react';
import type { PageProps } from '@/types';

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
    notes: string | null;
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
    termination_date: string | null;
    ctps_number: string | null;
    pis_pasep: string | null;
    salary: string | null;
    vacation_alert_days: number | null;
    notes: string | null;
    condominium: { id: string; name: string } | null;
    person: { id: string; name: string; cpf: string | null; email: string | null; phone: string | null } | null;
    creator: { id: string; name: string } | null;
    vacation_periods: VacationPeriod[];
}

interface VacationFormData {
    acquisition_start: string;
    acquisition_end: string;
    deadline_date: string;
    vacation_start: string;
    vacation_end: string;
    days: string;
    status: string;
    notes: string;
}

interface Props {
    employee: Employee;
    vacationStatuses: Record<string, string>;
}

const dateValue = (value: string | null) => value ? value.slice(0, 10) : '';
const fmtDate = (value: string | null) => value ? new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString('pt-BR') : '-';
const fmtMoney = (value: string | null) => value ? Number(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : '-';
const pad = (value: number) => String(value).padStart(2, '0');
const ymd = (date: Date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

function addDays(value: string, days: number) {
    if (!value) return '';
    const date = new Date(`${value}T00:00:00`);
    date.setDate(date.getDate() + days);
    return ymd(date);
}

function addYears(value: string, years: number) {
    if (!value) return '';
    const date = new Date(`${value}T00:00:00`);
    date.setFullYear(date.getFullYear() + years);
    return ymd(date);
}

function Info({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div>
            <p className="text-xs font-medium uppercase tracking-wide text-gray-500">{label}</p>
            <div className="mt-1 text-sm font-medium text-gray-900">{value || '-'}</div>
        </div>
    );
}

function VacationBadge({ period }: { period: VacationPeriod }) {
    if (period.deadline_status === 'overdue') {
        return <span className="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">Atrasada {period.days_until_deadline !== null ? `${Math.abs(period.days_until_deadline)}d` : ''}</span>;
    }

    if (period.deadline_status === 'due_soon') {
        return <span className="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">{period.days_until_deadline === 0 ? 'Vence hoje' : `Vence em ${period.days_until_deadline}d`}</span>;
    }

    if (period.deadline_status === 'ok') {
        return <span className="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Em dia</span>;
    }

    return <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{period.status_label}</span>;
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div>
            <label className="mb-1 block text-xs font-medium text-gray-600">{label}</label>
            {children}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

const inputClass = 'w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none';

function VacationPeriodEditor({
    period,
    statuses,
    canUpdate,
    canDelete,
}: {
    period: VacationPeriod;
    statuses: Record<string, string>;
    canUpdate: boolean;
    canDelete: boolean;
}) {
    const form = useForm<VacationFormData>({
        acquisition_start: dateValue(period.acquisition_start),
        acquisition_end: dateValue(period.acquisition_end),
        deadline_date: dateValue(period.deadline_date),
        vacation_start: dateValue(period.vacation_start),
        vacation_end: dateValue(period.vacation_end),
        days: String(period.days ?? 30),
        status: period.status,
        notes: period.notes ?? '',
    });

    const submit = () => form.put(route('employees.vacations.update', period.id), { preserveScroll: true });
    const destroy = () => {
        if (confirm('Remover este periodo de ferias?')) {
            router.delete(route('employees.vacations.destroy', period.id), { preserveScroll: true });
        }
    };

    return (
        <div className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
            <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <CalendarDays className="h-5 w-5 text-blue-600" />
                    <h3 className="text-sm font-semibold text-gray-900">
                        {fmtDate(period.acquisition_start)} a {fmtDate(period.acquisition_end)}
                    </h3>
                    <VacationBadge period={period} />
                </div>

                {canDelete && (
                    <button onClick={destroy} className="inline-flex items-center gap-1 self-start rounded-lg border border-red-100 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 sm:self-auto">
                        <Trash2 className="h-3.5 w-3.5" /> Remover
                    </button>
                )}
            </div>

            {canUpdate ? (
                <div className="grid gap-3 sm:grid-cols-3">
                    <Field label="Inicio aquisitivo" error={form.errors.acquisition_start}>
                        <input type="date" value={form.data.acquisition_start} onChange={(event) => form.setData('acquisition_start', event.target.value)} className={inputClass} />
                    </Field>
                    <Field label="Fim aquisitivo" error={form.errors.acquisition_end}>
                        <input type="date" value={form.data.acquisition_end} onChange={(event) => form.setData('acquisition_end', event.target.value)} className={inputClass} />
                    </Field>
                    <Field label="Prazo limite" error={form.errors.deadline_date}>
                        <input type="date" value={form.data.deadline_date} onChange={(event) => form.setData('deadline_date', event.target.value)} className={inputClass} />
                    </Field>
                    <Field label="Inicio das ferias" error={form.errors.vacation_start}>
                        <input type="date" value={form.data.vacation_start} onChange={(event) => form.setData('vacation_start', event.target.value)} className={inputClass} />
                    </Field>
                    <Field label="Fim das ferias" error={form.errors.vacation_end}>
                        <input type="date" value={form.data.vacation_end} onChange={(event) => form.setData('vacation_end', event.target.value)} className={inputClass} />
                    </Field>
                    <Field label="Dias" error={form.errors.days}>
                        <input type="number" min={1} max={30} value={form.data.days} onChange={(event) => form.setData('days', event.target.value)} className={inputClass} />
                    </Field>
                    <Field label="Status" error={form.errors.status}>
                        <select value={form.data.status} onChange={(event) => form.setData('status', event.target.value)} className={inputClass}>
                            {Object.entries(statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                    </Field>
                    <div className="sm:col-span-2">
                        <Field label="Observacoes" error={form.errors.notes}>
                            <input value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} className={inputClass} />
                        </Field>
                    </div>
                    <div className="sm:col-span-3">
                        <button onClick={submit} disabled={form.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                            {form.processing ? 'Salvando...' : 'Salvar periodo'}
                        </button>
                    </div>
                </div>
            ) : (
                <div className="grid gap-4 text-sm sm:grid-cols-4">
                    <Info label="Prazo" value={fmtDate(period.deadline_date)} />
                    <Info label="Gozo" value={period.vacation_start || period.vacation_end ? `${fmtDate(period.vacation_start)} a ${fmtDate(period.vacation_end)}` : '-'} />
                    <Info label="Dias" value={period.days} />
                    <Info label="Status" value={period.status_label} />
                </div>
            )}
        </div>
    );
}

export default function EmployeeShow({ employee, vacationStatuses }: Props) {
    const { auth } = usePage<PageProps>().props;
    const permissions = auth.user?.permissions ?? [];
    const can = (permission: string) => permissions.includes('*') || permissions.includes(permission);

    const firstStart = employee.vacation_periods[0]?.acquisition_end
        ? addDays(dateValue(employee.vacation_periods[0].acquisition_end), 1)
        : dateValue(employee.admission_date);
    const firstEnd = firstStart ? addDays(addYears(firstStart, 1), -1) : '';

    const newPeriod = useForm<VacationFormData>({
        acquisition_start: firstStart,
        acquisition_end: firstEnd,
        deadline_date: firstEnd ? addYears(firstEnd, 1) : '',
        vacation_start: '',
        vacation_end: '',
        days: '30',
        status: 'pending',
        notes: '',
    });

    const submitNewPeriod = () => newPeriod.post(route('employees.vacations.store', employee.id), {
        preserveScroll: true,
        onSuccess: () => newPeriod.reset('vacation_start', 'vacation_end', 'notes'),
    });

    const destroyEmployee = () => {
        if (confirm(`Remover o funcionario "${employee.name}"?`)) {
            router.delete(route('employees.destroy', employee.id));
        }
    };

    return (
        <AppLayout>
            <Head title={employee.name} />
            <div className="mx-auto max-w-5xl space-y-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <Link href={route('employees.index')} className="text-sm text-gray-500 hover:text-gray-700">Voltar</Link>
                        <div className="mt-1 flex items-center gap-2">
                            <BriefcaseBusiness className="h-6 w-6 text-blue-600" />
                            <h1 className="text-2xl font-bold text-gray-900">{employee.name}</h1>
                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{employee.status_label}</span>
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {can('employees:update') && (
                            <Link href={route('employees.edit', employee.id)} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <Pencil className="h-4 w-4" /> Editar
                            </Link>
                        )}
                        {can('employees:delete') && (
                            <button onClick={destroyEmployee} className="inline-flex items-center gap-2 rounded-lg border border-red-100 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                                <Trash2 className="h-4 w-4" /> Remover
                            </button>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <Info label="Condominio" value={employee.condominium?.name ?? '-'} />
                    </div>
                    <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <Info label="Cargo" value={employee.position ?? '-'} />
                    </div>
                    <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <Info label="Admissao" value={fmtDate(employee.admission_date)} />
                    </div>
                    <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <Info label="Alerta de ferias" value={`${employee.vacation_alert_days ?? 60} dias`} />
                    </div>
                </div>

                <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-700">Dados trabalhistas</h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Info label="Documento" value={employee.document ? maskCpfCnpj(employee.document) : '-'} />
                        <Info label="Telefone" value={employee.phone ? maskPhone(employee.phone) : '-'} />
                        <Info label="E-mail" value={employee.email ?? '-'} />
                        <Info label="Vinculo" value={employee.employment_type_label} />
                        <Info label="CTPS" value={employee.ctps_number ?? '-'} />
                        <Info label="PIS/PASEP" value={employee.pis_pasep ?? '-'} />
                        <Info label="Salario" value={fmtMoney(employee.salary)} />
                        <Info label="Desligamento" value={fmtDate(employee.termination_date)} />
                    </div>

                    {(employee.person || employee.creator || employee.notes) && (
                        <div className="mt-5 grid gap-4 border-t border-gray-100 pt-4 sm:grid-cols-3">
                            <Info label="Pessoa vinculada" value={employee.person?.name ?? '-'} />
                            <Info label="Criado por" value={employee.creator?.name ?? '-'} />
                            <Info label="Observacoes" value={employee.notes ?? '-'} />
                        </div>
                    )}
                </div>

                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-700">Periodos de ferias</h2>
                        <span className="text-xs text-gray-400">{employee.vacation_periods.length} periodo(s)</span>
                    </div>

                    {can('employees:update') && (
                        <div className="rounded-xl border border-blue-100 bg-blue-50/40 p-5">
                            <div className="mb-4 flex items-center gap-2">
                                <Plus className="h-5 w-5 text-blue-600" />
                                <h3 className="text-sm font-semibold text-gray-900">Novo periodo</h3>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-3">
                                <Field label="Inicio aquisitivo" error={newPeriod.errors.acquisition_start}>
                                    <input type="date" value={newPeriod.data.acquisition_start} onChange={(event) => newPeriod.setData('acquisition_start', event.target.value)} className={inputClass} />
                                </Field>
                                <Field label="Fim aquisitivo" error={newPeriod.errors.acquisition_end}>
                                    <input type="date" value={newPeriod.data.acquisition_end} onChange={(event) => newPeriod.setData('acquisition_end', event.target.value)} className={inputClass} />
                                </Field>
                                <Field label="Prazo limite" error={newPeriod.errors.deadline_date}>
                                    <input type="date" value={newPeriod.data.deadline_date} onChange={(event) => newPeriod.setData('deadline_date', event.target.value)} className={inputClass} />
                                </Field>
                                <Field label="Inicio das ferias" error={newPeriod.errors.vacation_start}>
                                    <input type="date" value={newPeriod.data.vacation_start} onChange={(event) => newPeriod.setData('vacation_start', event.target.value)} className={inputClass} />
                                </Field>
                                <Field label="Fim das ferias" error={newPeriod.errors.vacation_end}>
                                    <input type="date" value={newPeriod.data.vacation_end} onChange={(event) => newPeriod.setData('vacation_end', event.target.value)} className={inputClass} />
                                </Field>
                                <Field label="Dias" error={newPeriod.errors.days}>
                                    <input type="number" min={1} max={30} value={newPeriod.data.days} onChange={(event) => newPeriod.setData('days', event.target.value)} className={inputClass} />
                                </Field>
                                <Field label="Status" error={newPeriod.errors.status}>
                                    <select value={newPeriod.data.status} onChange={(event) => newPeriod.setData('status', event.target.value)} className={inputClass}>
                                        {Object.entries(vacationStatuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                    </select>
                                </Field>
                                <div className="sm:col-span-2">
                                    <Field label="Observacoes" error={newPeriod.errors.notes}>
                                        <input value={newPeriod.data.notes} onChange={(event) => newPeriod.setData('notes', event.target.value)} className={inputClass} />
                                    </Field>
                                </div>
                                <div className="sm:col-span-3">
                                    <button onClick={submitNewPeriod} disabled={newPeriod.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                                        {newPeriod.processing ? 'Salvando...' : 'Adicionar periodo'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    )}

                    {employee.vacation_periods.length === 0 && (
                        <div className="rounded-xl border border-gray-100 bg-white px-4 py-8 text-center text-sm text-gray-500 shadow-sm">
                            Nenhum periodo de ferias registrado.
                        </div>
                    )}

                    {employee.vacation_periods.map((period) => (
                        <VacationPeriodEditor
                            key={period.id}
                            period={period}
                            statuses={vacationStatuses}
                            canUpdate={can('employees:update')}
                            canDelete={can('employees:delete')}
                        />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
