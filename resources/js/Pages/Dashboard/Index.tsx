import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import { Building2, Users, HardDrive, TrendingUp } from 'lucide-react';

interface UsageStat {
    current: number;
    limit: number;
    unlimited: boolean;
    percentage: number;
    near_limit: boolean;
}

interface StorageStat {
    used_gb: number;
    quota_gb: number;
    percentage_used: number;
    is_near_limit: boolean;
    is_at_limit: boolean;
}

interface Props {
    stats: Record<string, UsageStat>;
    storage: StorageStat;
}

function StatCard({
    title,
    value,
    max,
    unlimited,
    percentage,
    nearLimit,
    icon: Icon,
    color,
}: {
    title: string;
    value: number;
    max: number;
    unlimited: boolean;
    percentage: number;
    nearLimit: boolean;
    icon: React.ElementType;
    color: string;
}) {
    return (
        <div className="rounded-xl bg-white p-6 shadow-sm border border-gray-100">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm text-gray-500">{title}</p>
                    <p className="mt-1 text-2xl font-semibold text-gray-900">
                        {value.toLocaleString('pt-BR')}
                        {!unlimited && (
                            <span className="text-sm font-normal text-gray-400 ml-1">
                                / {max.toLocaleString('pt-BR')}
                            </span>
                        )}
                        {unlimited && (
                            <span className="text-sm font-normal text-gray-400 ml-1">/ ∞</span>
                        )}
                    </p>
                </div>
                <div className={`rounded-xl p-3 ${color}`}>
                    <Icon className="h-6 w-6 text-white" />
                </div>
            </div>
            {!unlimited && (
                <div className="mt-4">
                    <div className="flex items-center justify-between text-xs text-gray-500 mb-1">
                        <span>{percentage}% utilizado</span>
                        {nearLimit && <span className="text-amber-600 font-medium">⚠ Próximo do limite</span>}
                    </div>
                    <div className="h-1.5 rounded-full bg-gray-100">
                        <div
                            className={`h-1.5 rounded-full transition-all ${
                                nearLimit ? 'bg-amber-500' : 'bg-blue-500'
                            }`}
                            style={{ width: `${Math.min(percentage, 100)}%` }}
                        />
                    </div>
                </div>
            )}
        </div>
    );
}

export default function Dashboard({ stats, storage }: Props) {
    const condominiums = stats?.condominiums;
    const units = stats?.units;
    const users = stats?.users;

    return (
        <AppLayout>
            <Head title="Dashboard" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Visão geral do sistema — {new Date().toLocaleDateString('pt-BR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
                    </p>
                </div>

                {/* KPI Cards */}
                {condominiums && (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard
                            title="Condomínios"
                            value={condominiums.current}
                            max={condominiums.limit}
                            unlimited={condominiums.unlimited}
                            percentage={condominiums.percentage}
                            nearLimit={condominiums.near_limit}
                            icon={Building2}
                            color="bg-blue-600"
                        />
                        {units && (
                            <StatCard
                                title="Unidades"
                                value={units.current}
                                max={units.limit}
                                unlimited={units.unlimited}
                                percentage={units.percentage}
                                nearLimit={units.near_limit}
                                icon={Building2}
                                color="bg-indigo-600"
                            />
                        )}
                        {users && (
                            <StatCard
                                title="Usuários"
                                value={users.current}
                                max={users.limit}
                                unlimited={users.unlimited}
                                percentage={users.percentage}
                                nearLimit={users.near_limit}
                                icon={Users}
                                color="bg-violet-600"
                            />
                        )}
                        {storage && (
                            <div className="rounded-xl bg-white p-6 shadow-sm border border-gray-100">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm text-gray-500">Armazenamento</p>
                                        <p className="mt-1 text-2xl font-semibold text-gray-900">
                                            {storage.used_gb.toFixed(1)} GB
                                            <span className="text-sm font-normal text-gray-400 ml-1">
                                                / {storage.quota_gb.toFixed(0)} GB
                                            </span>
                                        </p>
                                    </div>
                                    <div className="rounded-xl bg-emerald-600 p-3">
                                        <HardDrive className="h-6 w-6 text-white" />
                                    </div>
                                </div>
                                <div className="mt-4">
                                    <div className="flex items-center justify-between text-xs text-gray-500 mb-1">
                                        <span>{storage.percentage_used}% utilizado</span>
                                        {storage.is_near_limit && (
                                            <span className="text-amber-600 font-medium">⚠ Próximo do limite</span>
                                        )}
                                        {storage.is_at_limit && (
                                            <span className="text-red-600 font-medium">🔴 Limite atingido</span>
                                        )}
                                    </div>
                                    <div className="h-1.5 rounded-full bg-gray-100">
                                        <div
                                            className={`h-1.5 rounded-full transition-all ${
                                                storage.is_at_limit
                                                    ? 'bg-red-500'
                                                    : storage.is_near_limit
                                                    ? 'bg-amber-500'
                                                    : 'bg-emerald-500'
                                            }`}
                                            style={{ width: `${Math.min(storage.percentage_used, 100)}%` }}
                                        />
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* Empty State */}
                {!condominiums && (
                    <div className="rounded-xl border-2 border-dashed border-gray-200 bg-white p-12 text-center">
                        <Building2 className="mx-auto h-12 w-12 text-gray-400" />
                        <h3 className="mt-4 text-lg font-semibold text-gray-900">
                            Nenhum condomínio cadastrado ainda
                        </h3>
                        <p className="mt-2 text-sm text-gray-500">
                            Comece cadastrando o primeiro condomínio para ver as estatísticas aqui.
                        </p>
                        <button className="mt-6 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                            <Building2 className="h-4 w-4" />
                            Cadastrar primeiro condomínio
                        </button>
                    </div>
                )}

                {/* Welcome Card (quando não tem tenant) */}
                <div className="rounded-xl bg-gradient-to-r from-blue-600 to-blue-800 p-6 text-white">
                    <div className="flex items-center gap-4">
                        <TrendingUp className="h-8 w-8 opacity-80" />
                        <div>
                            <h2 className="text-lg font-semibold">SindÂncora — Gestão Condominial</h2>
                            <p className="mt-1 text-sm text-blue-100">
                                Sistema completo para administração de condomínios com segurança e eficiência.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
