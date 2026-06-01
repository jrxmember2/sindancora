import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm } from '@inertiajs/react';

interface Plan {
    id: string;
    name: string;
    display_name: string;
    price_monthly: string;
}

interface Props {
    plans: Plan[];
}

export default function TenantCreate({ plans }: Props) {
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

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/admin/tenants');
    }

    function Field({ label, name, type = 'text', required = false, hint }: {
        label: string; name: keyof typeof data; type?: string; required?: boolean; hint?: string;
    }) {
        return (
            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                    {label} {required && <span className="text-red-500">*</span>}
                </label>
                <input
                    type={type}
                    value={data[name] as string}
                    onChange={(e) => setData(name, e.target.value)}
                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
                {hint && <p className="mt-1 text-xs text-gray-500">{hint}</p>}
                {errors[name] && <p className="mt-1 text-xs text-red-600">{errors[name]}</p>}
            </div>
        );
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
                        <Field label="Nome da empresa" name="name" required />
                        <Field label="Slug (subdomínio)" name="slug" hint="Deixe em branco para gerar automaticamente. Ex: minha-empresa → minha-empresa.sindancora.com.br" />
                        <Field label="CNPJ" name="document" />
                        <Field label="E-mail" name="email" type="email" required />
                        <Field label="Telefone" name="phone" />
                    </div>

                    {/* Plano */}
                    <div className="rounded-xl bg-white p-6 shadow-sm border border-gray-100 space-y-4">
                        <h2 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Plano</h2>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Plano <span className="text-red-500">*</span></label>
                            <select
                                value={data.plan_id}
                                onChange={(e) => setData('plan_id', e.target.value)}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
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
                        <Field label="Nome" name="admin_name" required />
                        <Field label="E-mail" name="admin_email" type="email" required />
                        <Field label="Senha" name="admin_password" type="password" required />
                    </div>

                    <div className="flex items-center gap-3">
                        <button
                            type="submit"
                            disabled={processing}
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
