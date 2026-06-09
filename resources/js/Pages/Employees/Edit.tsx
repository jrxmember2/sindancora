import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import EmployeeForm, { type EmployeeFormData, type Option, type PersonOption } from './EmployeeForm';

interface Employee {
    id: string;
    condominium_id: string;
    person_id: string | null;
    name: string;
    document: string | null;
    email: string | null;
    phone: string | null;
    position: string | null;
    employment_type: string;
    status: string;
    admission_date: string | null;
    termination_date: string | null;
    ctps_number: string | null;
    pis_pasep: string | null;
    salary: string | null;
    vacation_alert_days: number | null;
    notes: string | null;
}

interface Props {
    employee: Employee;
    condominiums: Option[];
    persons: PersonOption[];
    statuses: Record<string, string>;
    employmentTypes: Record<string, string>;
}

const dateValue = (value: string | null) => value ? value.slice(0, 10) : '';

export default function EmployeeEdit({ employee, condominiums, persons, statuses, employmentTypes }: Props) {
    const form = useForm<EmployeeFormData>({
        condominium_id: employee.condominium_id,
        person_id: employee.person_id ?? '',
        name: employee.name,
        document: employee.document ?? '',
        email: employee.email ?? '',
        phone: employee.phone ?? '',
        position: employee.position ?? '',
        employment_type: employee.employment_type,
        status: employee.status,
        admission_date: dateValue(employee.admission_date),
        termination_date: dateValue(employee.termination_date),
        ctps_number: employee.ctps_number ?? '',
        pis_pasep: employee.pis_pasep ?? '',
        salary: employee.salary ?? '',
        vacation_alert_days: String(employee.vacation_alert_days ?? 60),
        notes: employee.notes ?? '',
    });

    return (
        <AppLayout>
            <Head title="Editar funcionario" />
            <div className="space-y-4">
                <div>
                    <Link href={route('employees.show', employee.id)} className="text-sm text-gray-500 hover:text-gray-700">Voltar</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Editar funcionario</h1>
                </div>

                <EmployeeForm
                    data={form.data}
                    setData={(key, value) => form.setData(key, value)}
                    errors={form.errors}
                    processing={form.processing}
                    onSubmit={() => form.put(route('employees.update', employee.id))}
                    submitLabel="Salvar"
                    backHref={route('employees.show', employee.id)}
                    condominiums={condominiums}
                    persons={persons}
                    statuses={statuses}
                    employmentTypes={employmentTypes}
                />
            </div>
        </AppLayout>
    );
}
