import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface PlanData {
    id: string;
    name: string;
    display_name: string;
    description: string | null;
    price_monthly: string | null;
    price_yearly: string | null;
    is_active: boolean;
    is_public: boolean;
    sort_order: number;
}

interface Props {
    plan: PlanData | null;
    limits: Record<string, number>;
    modules: string[];
    resourceLabels: Record<string, string>;
    moduleLabels: Record<string, string>;
}

const inputClass =
    'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';

export default function PlanForm({ plan, limits, modules, resourceLabels, moduleLabels }: Props) {
    const isEdit = !!plan;

    const initialLimits: Record<string, number> = {};
    Object.keys(resourceLabels).forEach((key) => {
        initialLimits[key] = limits?.[key] ?? 0;
    });

    const { data, setData, post, put, processing, errors } = useForm({
        name: plan?.name ?? '',
        display_name: plan?.display_name ?? '',
        description: plan?.description ?? '',
        price_monthly: plan?.price_monthly ?? '',
        price_yearly: plan?.price_yearly ?? '',
        is_active: plan?.is_active ?? true,
        is_public: plan?.is_public ?? false,
        sort_order: plan?.sort_order ?? 0,
        limits: initialLimits,
        modules: modules ?? [],
    });

    function setLimit(resource: string, value: number) {
        setData('limits', { ...data.limits, [resource]: value });
    }

    function toggleModule(module: string) {
        setData('modules', data.modules.includes(module)
            ? data.modules.filter((m) => m !== module)
            : [...data.modules, module]);
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (isEdit && plan) {
            put(`/admin/planos/${plan.id}`);
        } else {
            post('/admin/planos');
        }
    }

    return (
        <AdminLayout>
            <Head title={isEdit ? `Editar — ${plan?.display_name}` : 'Novo Plano'} />
            <div className="max-w-3xl space-y-6">
                <div className="flex items-center gap-4">
                    <Link href="/admin/planos" className="text-gray-400 hover:text-gray-600">
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <h1 className="text-2xl font-bold text-gray-900">{isEdit ? 'Editar Plano' : 'Novo Plano'}</h1>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Dados básicos */}
                    <div className="rounded-xl bg-white p-6 shadow-sm border border-gray-100 space-y-4">
                        <h2 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Dados do Plano</h2>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Identificador (slug) *</label>
                                <input value={data.name} onChange={(e) => setData('name', e.target.value)} className={inputClass} placeholder="ex: profissional" />
                                {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Nome de exibição *</label>
                                <input value={data.display_name} onChange={(e) => setData('display_name', e.target.value)} className={inputClass} placeholder="ex: Profissional" />
                                {errors.display_name && <p className="mt-1 text-xs text-red-600">{errors.display_name}</p>}
                            </div>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                            <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={2} className={`${inputClass} resize-none`} />
                            {errors.description && <p className="mt-1 text-xs text-red-600">{errors.description}</p>}
                        </div>
                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Preço mensal (R$)</label>
                                <input type="number" step="0.01" min="0" value={data.price_monthly} onChange={(e) => setData('price_monthly', e.target.value)} className={inputClass} placeholder="Vazio = sob consulta" />
                                {errors.price_monthly && <p className="mt-1 text-xs text-red-600">{errors.price_monthly}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Preço anual (R$)</label>
                                <input type="number" step="0.01" min="0" value={data.price_yearly} onChange={(e) => setData('price_yearly', e.target.value)} className={inputClass} />
                                {errors.price_yearly && <p className="mt-1 text-xs text-red-600">{errors.price_yearly}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Ordem</label>
                                <input type="number" min="0" value={data.sort_order} onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)} className={inputClass} />
                            </div>
                        </div>
                        <div className="flex items-center gap-6 pt-1">
                            <label className="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="rounded border-gray-300" />
                                Ativo
                            </label>
                            <label className="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" checked={data.is_public} onChange={(e) => setData('is_public', e.target.checked)} className="rounded border-gray-300" />
                                Público (aparece no site)
                            </label>
                        </div>
                    </div>

                    {/* Limites */}
                    <div className="rounded-xl bg-white p-6 shadow-sm border border-gray-100 space-y-4">
                        <div>
                            <h2 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Limites</h2>
                            <p className="mt-1 text-xs text-gray-500">Marque "Ilimitado" para liberar sem teto (armazena -1).</p>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            {Object.entries(resourceLabels).map(([resource, label]) => {
                                const unlimited = data.limits[resource] === -1;
                                return (
                                    <div key={resource} className="rounded-lg border border-gray-100 p-3">
                                        <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
                                        <div className="flex items-center gap-3">
                                            <input
                                                type="number"
                                                min="0"
                                                disabled={unlimited}
                                                value={unlimited ? '' : data.limits[resource]}
                                                onChange={(e) => setLimit(resource, parseInt(e.target.value) || 0)}
                                                className={`${inputClass} disabled:bg-gray-100`}
                                                placeholder={unlimited ? 'Ilimitado' : '0'}
                                            />
                                            <label className="flex items-center gap-1 text-xs text-gray-600 whitespace-nowrap">
                                                <input
                                                    type="checkbox"
                                                    checked={unlimited}
                                                    onChange={(e) => setLimit(resource, e.target.checked ? -1 : 0)}
                                                    className="rounded border-gray-300"
                                                />
                                                Ilimitado
                                            </label>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Módulos */}
                    <div className="rounded-xl bg-white p-6 shadow-sm border border-gray-100 space-y-4">
                        <h2 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">Módulos habilitados</h2>
                        <div className="grid grid-cols-3 gap-2">
                            {Object.entries(moduleLabels).map(([module, label]) => (
                                <label key={module} className="flex items-center gap-2 rounded-lg border border-gray-100 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={data.modules.includes(module)}
                                        onChange={() => toggleModule(module)}
                                        className="rounded border-gray-300"
                                    />
                                    {label}
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors"
                        >
                            {processing ? 'Salvando...' : isEdit ? 'Salvar alterações' : 'Criar plano'}
                        </button>
                        <Link href="/admin/planos" className="text-sm text-gray-600 hover:text-gray-900">Cancelar</Link>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
