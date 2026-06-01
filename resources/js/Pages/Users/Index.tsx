import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus, Search, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Role { id: string; display_name: string }
interface UserRole { role: Role }
interface User {
    id: string;
    name: string;
    email: string;
    status: string;
    user_roles: UserRole[];
    created_at: string;
}
interface Paginator {
    data: User[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
    links: { url: string | null; label: string; active: boolean }[];
}
interface Props {
    users: Paginator;
    filters: { search?: string; status?: string };
}

export default function UsersIndex({ users, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const { delete: destroy } = useForm();

    function applyFilter(params: Record<string, string>) {
        router.get('/usuarios', { ...filters, ...params }, { preserveState: true, replace: true });
    }

    function handleDelete(user: User) {
        if (!confirm(`Excluir "${user.name}"? Esta ação não pode ser desfeita.`)) return;
        destroy(`/usuarios/${user.id}`);
    }

    return (
        <AppLayout>
            <Head title="Usuários" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Usuários</h1>
                        <p className="mt-1 text-sm text-gray-500">{users.total} usuário{users.total !== 1 ? 's' : ''} cadastrado{users.total !== 1 ? 's' : ''}</p>
                    </div>
                    <Link
                        href="/usuarios/create"
                        className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
                    >
                        <Plus className="h-4 w-4" />
                        Novo Usuário
                    </Link>
                </div>

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
                        <option value="inactive">Inativo</option>
                    </select>
                </div>

                <div className="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-100">
                        <thead>
                            <tr className="bg-gray-50">
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Usuário</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Perfil</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Cadastro</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {users.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-4 py-12 text-center text-sm text-gray-500">Nenhum usuário encontrado.</td>
                                </tr>
                            )}
                            {users.data.map((user) => (
                                <tr key={user.id} className="hover:bg-gray-50 transition-colors">
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-3">
                                            <div className="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-blue-700 text-sm font-semibold">
                                                {user.name.charAt(0).toUpperCase()}
                                            </div>
                                            <div>
                                                <p className="text-sm font-medium text-gray-900">{user.name}</p>
                                                <p className="text-xs text-gray-500">{user.email}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {user.user_roles.length === 0 ? (
                                                <span className="text-xs text-gray-400">Sem perfil</span>
                                            ) : user.user_roles.map((ur, i) => (
                                                <span key={i} className="inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                                                    {ur.role.display_name}
                                                </span>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`text-xs font-medium ${user.status === 'active' ? 'text-green-600' : 'text-gray-400'}`}>
                                            {user.status === 'active' ? 'Ativo' : 'Inativo'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-500">
                                        {new Date(user.created_at).toLocaleDateString('pt-BR')}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-2">
                                            <Link href={`/usuarios/${user.id}/edit`} className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                                                <Pencil className="h-4 w-4" />
                                            </Link>
                                            <button onClick={() => handleDelete(user)} className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {users.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-gray-500">
                            Mostrando {(users.current_page - 1) * users.per_page + 1}–{Math.min(users.current_page * users.per_page, users.total)} de {users.total}
                        </p>
                        <div className="flex gap-1">
                            {users.links.map((link, i) => (
                                link.url ? (
                                    <Link key={i} href={link.url} className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-blue-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50'}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                                ) : (
                                    <span key={i} className="rounded px-3 py-1 text-sm bg-white border border-gray-200 text-gray-400" dangerouslySetInnerHTML={{ __html: link.label }} />
                                )
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
