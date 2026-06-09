import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import EmployeeForm, { type EmployeeFormData, type Option, type PersonOption } from './EmployeeForm';

interface Props {
    condominiums: Option[];
    persons: PersonOption[];
    statuses: Record<string, string>;
    employmentTypes: Record<string, string>;
}

export default function EmployeeCreate({ condominiums, persons, statuses, employmentTypes }: Props) {
    const form = useForm<EmployeeFormData>({
        condominium_id: condominiums.length === 1 ? condominiums[0].value : '',
        person_id: '',
        name: '',
        document: '',
        email: '',
        phone: '',
        position: '',
        employment_type: 'clt',
        status: 'active',
        admission_date: '',
        termination_date: '',
        ctps_number: '',
        pis_pasep: '',
        salary: '',
        vacation_alert_days: '60',
        notes: '',
        create_initial_vacation_period: true,
    });

    return (
        <AppLayout>
            <Head title="Novo funcionario" />
            <div className="space-y-4">
                <div>
                    <Link href={route('employees.index')} className="text-sm text-gray-500 hover:text-gray-700">Voltar</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Novo funcionario</h1>
                </div>

                <EmployeeForm
                    data={form.data}
                    setData={(key, value) => form.setData(key, value)}
                    errors={form.errors}
                    processing={form.processing}
                    onSubmit={() => form.post(route('employees.store'))}
                    submitLabel="Cadastrar"
                    backHref={route('employees.index')}
                    condominiums={condominiums}
                    persons={persons}
                    statuses={statuses}
                    employmentTypes={employmentTypes}
                    allowInitialVacation
                />
            </div>
        </AppLayout>
    );
}
