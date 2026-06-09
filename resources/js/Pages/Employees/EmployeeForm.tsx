import { maskCpfCnpj, maskPhone } from '@/lib/masks';
import { Link } from '@inertiajs/react';

export interface Option {
    value: string;
    label: string;
}

export interface PersonOption extends Option {
    document: string | null;
    email: string | null;
    phone: string | null;
}

export interface EmployeeFormData {
    condominium_id: string;
    person_id: string;
    name: string;
    document: string;
    email: string;
    phone: string;
    position: string;
    employment_type: string;
    status: string;
    admission_date: string;
    termination_date: string;
    ctps_number: string;
    pis_pasep: string;
    salary: string;
    vacation_alert_days: string;
    notes: string;
    create_initial_vacation_period?: boolean;
}

interface Props {
    data: EmployeeFormData;
    setData: (key: keyof EmployeeFormData, value: string | boolean) => void;
    errors: Partial<Record<keyof EmployeeFormData, string>>;
    processing: boolean;
    onSubmit: () => void;
    submitLabel: string;
    backHref: string;
    condominiums: Option[];
    persons: PersonOption[];
    statuses: Record<string, string>;
    employmentTypes: Record<string, string>;
    allowInitialVacation?: boolean;
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
            {children}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

function Input(props: React.InputHTMLAttributes<HTMLInputElement>) {
    return <input className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" {...props} />;
}

const inputClass = 'w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';

export default function EmployeeForm({
    data,
    setData,
    errors,
    processing,
    onSubmit,
    submitLabel,
    backHref,
    condominiums,
    persons,
    statuses,
    employmentTypes,
    allowInitialVacation = false,
}: Props) {
    const selectPerson = (personId: string) => {
        setData('person_id', personId);

        const person = persons.find((option) => option.value === personId);
        if (!person) return;

        if (!data.name) setData('name', person.label);
        if (!data.document && person.document) setData('document', maskCpfCnpj(person.document));
        if (!data.email && person.email) setData('email', person.email);
        if (!data.phone && person.phone) setData('phone', maskPhone(person.phone));
    };

    return (
        <div className="mx-auto max-w-3xl space-y-6">
            <div className="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-700">Dados do funcionario</h2>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Condominio *" error={errors.condominium_id}>
                        <select value={data.condominium_id} onChange={(event) => setData('condominium_id', event.target.value)} className={inputClass}>
                            <option value="">Selecione...</option>
                            {condominiums.map((condominium) => <option key={condominium.value} value={condominium.value}>{condominium.label}</option>)}
                        </select>
                    </Field>

                    <Field label="Pessoa vinculada" error={errors.person_id}>
                        <select value={data.person_id} onChange={(event) => selectPerson(event.target.value)} className={inputClass}>
                            <option value="">Sem vinculo</option>
                            {persons.map((person) => <option key={person.value} value={person.value}>{person.label}</option>)}
                        </select>
                    </Field>
                </div>

                <Field label="Nome *" error={errors.name}>
                    <Input value={data.name} onChange={(event) => setData('name', event.target.value)} maxLength={150} />
                </Field>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="CPF / CNPJ" error={errors.document}>
                        <Input value={maskCpfCnpj(data.document)} onChange={(event) => setData('document', maskCpfCnpj(event.target.value))} />
                    </Field>

                    <Field label="Cargo / funcao" error={errors.position}>
                        <Input value={data.position} onChange={(event) => setData('position', event.target.value)} maxLength={100} />
                    </Field>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="E-mail" error={errors.email}>
                        <Input type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} maxLength={150} />
                    </Field>

                    <Field label="Telefone" error={errors.phone}>
                        <Input value={maskPhone(data.phone)} onChange={(event) => setData('phone', maskPhone(event.target.value))} maxLength={30} />
                    </Field>
                </div>
            </div>

            <div className="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-700">Contrato</h2>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Tipo de vinculo *" error={errors.employment_type}>
                        <select value={data.employment_type} onChange={(event) => setData('employment_type', event.target.value)} className={inputClass}>
                            {Object.entries(employmentTypes).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                    </Field>

                    <Field label="Status *" error={errors.status}>
                        <select value={data.status} onChange={(event) => setData('status', event.target.value)} className={inputClass}>
                            {Object.entries(statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                    </Field>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Admissao *" error={errors.admission_date}>
                        <Input type="date" value={data.admission_date} onChange={(event) => setData('admission_date', event.target.value)} />
                    </Field>

                    <Field label="Desligamento" error={errors.termination_date}>
                        <Input type="date" value={data.termination_date} onChange={(event) => setData('termination_date', event.target.value)} />
                    </Field>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Field label="CTPS" error={errors.ctps_number}>
                        <Input value={data.ctps_number} onChange={(event) => setData('ctps_number', event.target.value)} maxLength={40} />
                    </Field>

                    <Field label="PIS/PASEP" error={errors.pis_pasep}>
                        <Input value={data.pis_pasep} onChange={(event) => setData('pis_pasep', event.target.value)} maxLength={40} />
                    </Field>

                    <Field label="Salario (R$)" error={errors.salary}>
                        <Input type="number" min={0} step="0.01" value={data.salary} onChange={(event) => setData('salary', event.target.value)} />
                    </Field>
                </div>
            </div>

            <div className="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-700">Ferias</h2>

                <Field label="Alerta de ferias (dias antes do prazo)" error={errors.vacation_alert_days}>
                    <Input type="number" min={0} max={365} value={data.vacation_alert_days} onChange={(event) => setData('vacation_alert_days', event.target.value)} />
                </Field>

                {allowInitialVacation && (
                    <label className="flex items-center gap-2 text-sm text-gray-700">
                        <input
                            type="checkbox"
                            checked={Boolean(data.create_initial_vacation_period)}
                            onChange={(event) => setData('create_initial_vacation_period', event.target.checked)}
                            className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        />
                        Criar primeiro periodo aquisitivo automaticamente
                    </label>
                )}
            </div>

            <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <Field label="Observacoes" error={errors.notes}>
                    <textarea
                        value={data.notes}
                        onChange={(event) => setData('notes', event.target.value)}
                        rows={4}
                        className="w-full resize-none rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    />
                </Field>
            </div>

            <div className="flex justify-between">
                <Link href={backHref} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">Cancelar</Link>
                <button onClick={onSubmit} disabled={processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50">
                    {processing ? 'Salvando...' : submitLabel}
                </button>
            </div>
        </div>
    );
}
