import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Pencil, Power } from 'lucide-react';

interface Plan {
    id: string;
    name: string;
    display_name: string;
    price_monthly: string | null;
    price_yearly: string | null;
    is_active: boolean;
    is_public: boolean;
    sort_order: number;
    tenants_count: number;
}

interface Props {
    plans: Plan[];
}

function formatPrice(value: string | null): string {
    if (value === null) return 'Sob consulta';
    return `R$ ${parseFloat(value).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
}

export default function PlansIndex({ plans }: Props) {
    const toggle = (plan: Plan) => {
        const verb = plan.is_active ? 'desativar' : 'ativar';
        if (confirm(`Deseja ${verb} o plano "${plan.display_name}"?`)) {
            router.patch(`/admin/planos/${plan.id}/toggle`);
        }
    };

    return (
        <AdminLayout>
            <Head title="Admin — Planos" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Planos</h1>
                        <p className="mt-1 text-sm text-gray-500">{plans.length} plano{plans.length !== 1 ? 's' : ''} cadastrado{plans.length !== 1 ? 's' : ''}</p>
                    </div>
                    <Link
                        href="/admin/planos/create"
                        className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
                    >
                        <Plus className="h-4 w-4" />
                        Novo Plano
                    </Link>
                </div>

                <div className="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-100">
                        <thead>
                            <tr className="bg-gray-50">
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Plano</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Mensal</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Tenants</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Visibilidade</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {plans.length === 0 && (
                                <tr><td colSpan={6} className="px-4 py-12 text-center text-sm text-gray-500">Nenhum plano cadastrado.</td></tr>
                            )}
                            {plans.map((plan) => (
                                <tr key={plan.id} className="hover:bg-gray-50 transition-colors">
                                    <td className="px-4 py-3">
                                        <p className="text-sm font-medium text-gray-900">{plan.display_name}</p>
                                        <p className="text-xs text-gray-500">{plan.name}</p>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-700">{formatPrice(plan.price_monthly)}</td>
                                    <td className="px-4 py-3 text-sm text-gray-700">{plan.tenants_count}</td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${plan.is_public ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-600'}`}>
                                            {plan.is_public ? 'Público' : 'Privado'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className="inline-flex items-center gap-1.5 text-xs font-medium text-gray-700">
                                            <span className={`h-2.5 w-2.5 rounded-full ${plan.is_active ? 'bg-green-500' : 'bg-red-500'}`} />
                                            {plan.is_active ? 'Ativo' : 'Inativo'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-2">
                                            <Link href={`/admin/planos/${plan.id}/edit`} className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600" title="Editar">
                                                <Pencil className="h-4 w-4" />
                                            </Link>
                                            <button onClick={() => toggle(plan)} className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600" title={plan.is_active ? 'Desativar' : 'Ativar'}>
                                                <Power className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminLayout>
    );
}
