import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Building2, Plus, Search, CheckCircle, AlertCircle, XCircle } from 'lucide-react';
import { useState } from 'react';

interface Tenant {
    id: string;
    name: string;
    slug: string;
    email: string;
    status: string;
    users_count: number;
    plan: { display_name: string } | null;
    domains: { domain: string }[];
    created_at: string;
}

interface Paginator {
    data: Tenant[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    tenants: Paginator;
    filters: { search?: string; status?: string };
}

const STATUS_LABELS: Record<string, { label: string; icon: React.ElementType; color: string }> = {
    active: { label: 'Ativo', icon: CheckCircle, color: 'text-green-600' },
    suspended: { label: 'Suspenso', icon: AlertCircle, color: 'text-amber-600' },
    cancelled: { label: 'Cancelado', icon: XCircle, color: 'text-red-600' },
    trial: { label: 'Trial', icon: CheckCircle, color: 'text-blue-600' },
};

export default function TenantsIndex({ tenants, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    function applyFilter(params: Record<string, string>) {
        router.get('/admin/tenants', { ...filters, ...params }, { preserveState: true, replace: true });
    }

    return (
        <AdminLayout>
            <Head title="Admin — Tenants" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Tenants</h1>
                        <p className="mt-1 text-sm text-gray-500">{tenants.total} tenant{tenants.total !== 1 ? 's' : ''} cadastrado{tenants.total !== 1 ? 's' : ''}</p>
                    </div>
                    <Link
                        href="/admin/tenants/create"
                        className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
                    >
                        <Plus className="h-4 w-4" />
                        Novo Tenant
                    </Link>
                </div>

                {/* Filtros */}
                <div className="flex gap-3">
                    <div className="relative flex-1 max-w-xs">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Buscar por nome ou e-mail..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && applyFilter({ search })}
                            className="w-full rounded-lg border border-gray-300 py-2 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                    </div>
                    <select
                        value={filters.status ?? ''}
                        onChange={(e) => applyFilter({ status: e.target.value, search })}
                        className="rounded-lg border border-gray-300 py-2 px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    >
                        <option value="">Todos os status</option>
                        <option value="active">Ativo</option>
                        <option value="suspended">Suspenso</option>
                        <option value="trial">Trial</option>
                        <option value="cancelled">Cancelado</option>
                    </select>
                </div>

                {/* Tabela */}
                <div className="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-100">
                        <thead>
                            <tr className="bg-gray-50">
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Tenant</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Plano</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Usuários</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Criado em</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {tenants.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-12 text-center text-sm text-gray-500">
                                        Nenhum tenant encontrado.
                                    </td>
                                </tr>
                            )}
                            {tenants.data.map((tenant) => {
                                const status = STATUS_LABELS[tenant.status] ?? STATUS_LABELS.active;
                                const StatusIcon = status.icon;
                                const subdomain = tenant.domains[0]?.domain ?? `${tenant.slug}.sindancora.com.br`;
                                return (
                                    <tr key={tenant.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-blue-700 text-sm font-semibold">
                                                    {tenant.name.charAt(0).toUpperCase()}
                                                </div>
                                                <div>
                                                    <p className="text-sm font-medium text-gray-900">{tenant.name}</p>
                                                    <p className="text-xs text-gray-500">{subdomain}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-700">{tenant.plan?.display_name ?? '—'}</td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-flex items-center gap-1 text-xs font-medium ${status.color}`}>
                                                <StatusIcon className="h-3.5 w-3.5" />
                                                {status.label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-700">{tenant.users_count}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500">
                                            {new Date(tenant.created_at).toLocaleDateString('pt-BR')}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Link
                                                href={`/admin/tenants/${tenant.id}`}
                                                className="text-xs font-medium text-blue-600 hover:text-blue-800"
                                            >
                                                Ver detalhes
                                            </Link>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {/* Paginação */}
                {tenants.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-gray-500">
                            Mostrando {(tenants.current_page - 1) * tenants.per_page + 1}–{Math.min(tenants.current_page * tenants.per_page, tenants.total)} de {tenants.total}
                        </p>
                        <div className="flex gap-1">
                            {tenants.links.map((link, i) => (
                                link.url ? (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-blue-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50'}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span key={i} className="rounded px-3 py-1 text-sm bg-white border border-gray-200 text-gray-400" dangerouslySetInnerHTML={{ __html: link.label }} />
                                )
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
