import AdminLayout from '@/Layouts/AdminLayout';
import { isValidCpfCnpj, isValidEmail, maskCpfCnpj, maskPhone } from '@/lib/masks';
import { Head, useForm } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';

interface Plan {
    id: string;
    name: string;
    display_name: string;
    price_monthly: string;
}

interface Props {
    plans: Plan[];
}

const inputClass =
    'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';

// Field em nível de módulo: definir dentro do componente recriava o input a cada tecla
// e fazia perder o foco.
function Field({
    label, value, onChange, type = 'text', required = false, hint, error, placeholder, rightSlot,
}: {
    label: string;
    value: string;
    onChange: (v: string) => void;
    type?: string;
    required?: boolean;
    hint?: string;
    error?: string;
    placeholder?: string;
    rightSlot?: React.ReactNode;
}) {
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
                {label} {required && <span className="text-red-500">*</span>}
            </label>
            <div className="relative">
                <input
                    type={type}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={placeholder}
                    className={inputClass + (rightSlot ? ' pr-10' : '')}
                />
                {rightSlot}
            </div>
            {hint && <p className="mt-1 text-xs text-gray-500">{hint}</p>}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

export default function TenantCreate({ plans }: Props) {
    const [showPassword, setShowPassword] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        slug: '',
        document: '',
        email: '',
        phone: '',
        plan_id: plans[0]?.id ?? '',
        admin_name: '',
        admin_email: '',
        admin_password: '',
    });

    const emailInvalid = data.email.length > 0 && !isValidEmail(data.email);
    const adminEmailInvalid = data.admin_email.length > 0 && !isValidEmail(data.admin_email);
    const documentInvalid = data.document.length > 0 && !isValidCpfCnpj(data.document);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/admin/tenants');
    }

    return (
        <AdminLayout>
            <Head title="Admin — Novo Tenant" />
            <div className="max-w-2xl space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Novo Tenant</h1>
                    <p className="mt-1 text-sm text-gray-500">Criação manual de tenant pelo Super Admin</p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Dados da empresa */}
                    <div className="rounded-xl bg-white p-6 shadow-sm border border-gray-100 space-y-4">
                        <h2 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Dados da Empresa</h2>
                        <Field label="Nome da empresa" required value={data.name} onChange={(v) => setData('name', v)} error={errors.name} />
                        <Field
                            label="Slug (subdomínio)"
                            value={data.slug}
                            onChange={(v) => setData('slug', v)}
                            error={errors.slug}
                            hint="Deixe em branco para gerar automaticamente. Ex: minha-empresa → minha-empresa.sindancora.com.br"
                        />
                        <Field
                            label="CPF/CNPJ"
                            value={data.document}
                            onChange={(v) => setData('document', maskCpfCnpj(v))}
                            error={errors.document ?? (documentInvalid ? 'CPF/CNPJ inválido.' : undefined)}
                            placeholder="CPF ou CNPJ"
                            hint="Síndico pessoa física pode usar apenas CPF."
                        />
                        <Field
                            label="E-mail"
                            type="email"
                            required
                            value={data.email}
                            onChange={(v) => setData('email', v)}
                            error={errors.email ?? (emailInvalid ? 'E-mail inválido (precisa conter @ e domínio).' : undefined)}
                        />
                        <Field
                            label="Telefone"
                            value={data.phone}
                            onChange={(v) => setData('phone', maskPhone(v))}
                            error={errors.phone}
                            placeholder="(00) 00000-0000"
                        />
                    </div>

                    {/* Plano */}
                    <div className="rounded-xl bg-white p-6 shadow-sm border border-gray-100 space-y-4">
                        <h2 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Plano</h2>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Plano <span className="text-red-500">*</span></label>
                            <select
                                value={data.plan_id}
                                onChange={(e) => setData('plan_id', e.target.value)}
                                className={inputClass}
                            >
                                {plans.map((plan) => (
                                    <option key={plan.id} value={plan.id}>
                                        {plan.display_name} — R$ {parseFloat(plan.price_monthly).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}/mês
                                    </option>
                                ))}
                            </select>
                            {errors.plan_id && <p className="mt-1 text-xs text-red-600">{errors.plan_id}</p>}
                        </div>
                    </div>

                    {/* Usuário admin */}
                    <div className="rounded-xl bg-white p-6 shadow-sm border border-gray-100 space-y-4">
                        <h2 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Usuário Administrador</h2>
                        <Field label="Nome" required value={data.admin_name} onChange={(v) => setData('admin_name', v)} error={errors.admin_name} />
                        <Field
                            label="E-mail"
                            type="email"
                            required
                            value={data.admin_email}
                            onChange={(v) => setData('admin_email', v)}
                            error={errors.admin_email ?? (adminEmailInvalid ? 'E-mail inválido (precisa conter @ e domínio).' : undefined)}
                        />
                        <Field
                            label="Senha"
                            type={showPassword ? 'text' : 'password'}
                            required
                            value={data.admin_password}
                            onChange={(v) => setData('admin_password', v)}
                            error={errors.admin_password}
                            hint="Mínimo de 8 caracteres."
                            rightSlot={
                                <button
                                    type="button"
                                    onClick={() => setShowPassword((s) => !s)}
                                    className="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
                                    tabIndex={-1}
                                    aria-label={showPassword ? 'Ocultar senha' : 'Mostrar senha'}
                                >
                                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                </button>
                            }
                        />
                    </div>

                    <div className="flex items-center gap-3">
                        <button
                            type="submit"
                            disabled={processing || emailInvalid || adminEmailInvalid || documentInvalid}
                            className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors"
                        >
                            {processing ? 'Criando...' : 'Criar Tenant'}
                        </button>
                        <a href="/admin/tenants" className="text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
