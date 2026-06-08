import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { CheckCircle, AlertCircle, ArrowLeft, Sparkles } from 'lucide-react';

interface User {
    id: string;
    name: string;
    email: string;
    status: string;
    created_at: string;
}

interface Tenant {
    id: string;
    name: string;
    slug: string;
    email: string;
    phone: string;
    document: string;
    status: string;
    plan: { id: string; display_name: string } | null;
    domains: { id: string; domain: string; type: string }[];
    users: User[];
    created_at: string;
}

interface Props {
    tenant: Tenant;
    plans: { id: string; name: string; display_name: string }[];
    aiUsage: {
        current: number;
        limit: number;
        unlimited: boolean;
        remaining: number | null;
        percentage: number;
        exhausted: boolean;
        reset_at: string | null;
        has_override: boolean;
    };
}

export default function TenantShow({ tenant, plans, aiUsage }: Props) {
    const isActive = tenant.status === 'active';

    const planForm = useForm({ plan_id: tenant.plan?.id ?? '' });
    const aiLimitForm = useForm({ limit_value: aiUsage.limit });
    const aiUnlimited = aiLimitForm.data.limit_value === -1;
    const aiResetAt = aiUsage.reset_at ? new Date(aiUsage.reset_at).toLocaleDateString('pt-BR') : '—';

    function toggleStatus() {
        router.patch(isActive ? `/admin/tenants/${tenant.id}/suspend` : `/admin/tenants/${tenant.id}/activate`);
    }

    function changePlan(e: React.FormEvent) {
        e.preventDefault();
        planForm.patch(`/admin/tenants/${tenant.id}/plan`);
    }

    function updateAiLimit(e: React.FormEvent) {
        e.preventDefault();
        aiLimitForm.patch(`/admin/tenants/${tenant.id}/ai-limit`, { preserveScroll: true });
    }

    function removeAiLimit() {
        router.delete(`/admin/tenants/${tenant.id}/ai-limit`, { preserveScroll: true });
    }

    return (
        <AdminLayout>
            <Head title={`Admin — ${tenant.name}`} />
            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Link href="/admin/tenants" className="text-gray-400 hover:text-gray-600">
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <div className="flex-1">
                        <h1 className="text-2xl font-bold text-gray-900">{tenant.name}</h1>
                        <p className="mt-0.5 text-sm text-gray-500">{tenant.domains[0]?.domain ?? `${tenant.slug}.sindancora.com.br`}</p>
                    </div>
                    <button
                        onClick={toggleStatus}
                        className={`inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors ${isActive ? 'bg-amber-100 text-amber-800 hover:bg-amber-200' : 'bg-green-100 text-green-800 hover:bg-green-200'}`}
                    >
                        {isActive ? <><AlertCircle className="h-4 w-4" /> Suspender</> : <><CheckCircle className="h-4 w-4" /> Reativar</>}
                    </button>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Dados gerais */}
                    <div className="rounded-xl bg-white p-6 shadow-sm border border-gray-100">
                        <h2 className="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-4">Dados Gerais</h2>
                        <dl className="space-y-3">
                            {[
                                { label: 'E-mail', value: tenant.email },
                                { label: 'Telefone', value: tenant.phone ?? '—' },
                                { label: 'CNPJ', value: tenant.document ?? '—' },
                                { label: 'Status', value: isActive ? 'Ativo' : 'Suspenso' },
                                { label: 'Criado em', value: new Date(tenant.created_at).toLocaleDateString('pt-BR') },
                            ].map(({ label, value }) => (
                                <div key={label} className="flex justify-between text-sm">
                                    <dt className="text-gray-500">{label}</dt>
                                    <dd className="font-medium text-gray-900">{value}</dd>
                                </div>
                            ))}
                        </dl>
                    </div>

                    {/* Alterar plano */}
                    <div className="rounded-xl bg-white p-6 shadow-sm border border-gray-100">
                        <h2 className="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-4">Plano</h2>
                        <p className="text-sm text-gray-600 mb-4">Plano atual: <strong>{tenant.plan?.display_name ?? '—'}</strong></p>
                        <form onSubmit={changePlan} className="flex gap-3">
                            <select
                                value={planForm.data.plan_id}
                                onChange={(e) => planForm.setData('plan_id', e.target.value)}
                                className="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            >
                                {plans.map((plan) => (
                                    <option key={plan.id} value={plan.id}>{plan.display_name}</option>
                                ))}
                            </select>
                            <button
                                type="submit"
                                disabled={planForm.processing}
                                className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                            >
                                Alterar
                            </button>
                        </form>
                    </div>

                    <div className="rounded-xl bg-white p-6 shadow-sm border border-gray-100 lg:col-span-2">
                        <div className="mb-4 flex items-center justify-between gap-4">
                            <h2 className="flex items-center gap-2 text-sm font-semibold text-gray-900 uppercase tracking-wide">
                                <Sparkles className="h-4 w-4 text-blue-600" /> Limite mensal de IA
                            </h2>
                            <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${aiUsage.exhausted ? 'bg-red-100 text-red-700' : 'bg-blue-50 text-blue-700'}`}>
                                {aiUsage.unlimited ? 'Ilimitado' : `${aiUsage.remaining} restantes`}
                            </span>
                        </div>

                        <div className="mb-4 grid gap-3 text-sm sm:grid-cols-4">
                            <div>
                                <p className="text-xs text-gray-500">Usado</p>
                                <p className="font-semibold text-gray-900">{aiUsage.current}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Limite</p>
                                <p className="font-semibold text-gray-900">{aiUsage.unlimited ? 'Ilimitado' : aiUsage.limit}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Renova em</p>
                                <p className="font-semibold text-gray-900">{aiResetAt}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500">Origem</p>
                                <p className="font-semibold text-gray-900">{aiUsage.has_override ? 'Override do tenant' : 'Plano'}</p>
                            </div>
                        </div>

                        {!aiUsage.unlimited && (
                            <div className="mb-4 h-2 overflow-hidden rounded-full bg-gray-100">
                                <div className={`h-full ${aiUsage.exhausted ? 'bg-red-500' : 'bg-blue-600'}`} style={{ width: `${aiUsage.percentage}%` }} />
                            </div>
                        )}

                        <form onSubmit={updateAiLimit} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div className="flex-1">
                                <label className="mb-1 block text-sm font-medium text-gray-700">Interações por mês</label>
                                <input
                                    type="number"
                                    min="0"
                                    disabled={aiUnlimited}
                                    value={aiUnlimited ? '' : aiLimitForm.data.limit_value}
                                    onChange={(e) => aiLimitForm.setData('limit_value', parseInt(e.target.value) || 0)}
                                    className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:bg-gray-100"
                                    placeholder={aiUnlimited ? 'Ilimitado' : '0'}
                                />
                            </div>
                            <label className="flex items-center gap-2 pb-2 text-sm text-gray-700">
                                <input
                                    type="checkbox"
                                    checked={aiUnlimited}
                                    onChange={(e) => aiLimitForm.setData('limit_value', e.target.checked ? -1 : 0)}
                                    className="rounded border-gray-300"
                                />
                                Ilimitado
                            </label>
                            <button type="submit" disabled={aiLimitForm.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                                Salvar limite
                            </button>
                            {aiUsage.has_override && (
                                <button type="button" onClick={removeAiLimit} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Usar plano
                                </button>
                            )}
                        </form>
                    </div>

                    {/* Usuários */}
                    <div className="rounded-xl bg-white p-6 shadow-sm border border-gray-100 lg:col-span-2">
                        <h2 className="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-4">Usuários Recentes</h2>
                        {tenant.users.length === 0 ? (
                            <p className="text-sm text-gray-500">Nenhum usuário cadastrado.</p>
                        ) : (
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="border-b border-gray-100">
                                        <th className="pb-2 text-left font-medium text-gray-500">Nome</th>
                                        <th className="pb-2 text-left font-medium text-gray-500">E-mail</th>
                                        <th className="pb-2 text-left font-medium text-gray-500">Status</th>
                                        <th className="pb-2 text-left font-medium text-gray-500">Cadastro</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50">
                                    {tenant.users.map((user) => (
                                        <tr key={user.id}>
                                            <td className="py-2 font-medium text-gray-900">{user.name}</td>
                                            <td className="py-2 text-gray-600">{user.email}</td>
                                            <td className="py-2">
                                                <span className={`text-xs font-medium ${user.status === 'active' ? 'text-green-600' : 'text-gray-400'}`}>
                                                    {user.status === 'active' ? 'Ativo' : 'Inativo'}
                                                </span>
                                            </td>
                                            <td className="py-2 text-gray-500">{new Date(user.created_at).toLocaleDateString('pt-BR')}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
