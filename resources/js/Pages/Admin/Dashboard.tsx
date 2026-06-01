import AdminLayout from '@/Layouts/AdminLayout';
import { Head } from '@inertiajs/react';
import { Building2, Users, TrendingUp, AlertCircle } from 'lucide-react';

interface Props {
    stats: {
        total_tenants: number;
        active_tenants: number;
        suspended_tenants: number;
        total_users: number;
        total_plans: number;
    };
}

function StatCard({ title, value, icon: Icon, color }: { title: string; value: number; icon: React.ElementType; color: string }) {
    return (
        <div className="rounded-xl bg-white p-6 shadow-sm border border-gray-100">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm text-gray-500">{title}</p>
                    <p className="mt-1 text-3xl font-bold text-gray-900">{value.toLocaleString('pt-BR')}</p>
                </div>
                <div className={`rounded-xl p-3 ${color}`}>
                    <Icon className="h-6 w-6 text-white" />
                </div>
            </div>
        </div>
    );
}

export default function AdminDashboard({ stats }: Props) {
    return (
        <AdminLayout>
            <Head title="Admin — Dashboard" />
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
                    <p className="mt-1 text-sm text-gray-500">Visão geral do SaaS</p>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard title="Tenants ativos" value={stats.active_tenants} icon={Building2} color="bg-blue-600" />
                    <StatCard title="Tenants suspensos" value={stats.suspended_tenants} icon={AlertCircle} color="bg-amber-500" />
                    <StatCard title="Total de usuários" value={stats.total_users} icon={Users} color="bg-violet-600" />
                    <StatCard title="Total de tenants" value={stats.total_tenants} icon={TrendingUp} color="bg-emerald-600" />
                </div>
            </div>
        </AdminLayout>
    );
}
