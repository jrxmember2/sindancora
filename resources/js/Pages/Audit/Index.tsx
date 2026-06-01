import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface AuditUser { id: string; name: string; email: string }
interface AuditLog {
    id: string;
    action: string;
    entity: string;
    entity_id: string | null;
    ip_address: string | null;
    created_at: string;
    user: AuditUser | null;
}
interface Paginator {
    data: AuditLog[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
    links: { url: string | null; label: string; active: boolean }[];
}
interface Props {
    logs: Paginator;
    users: { id: string; name: string }[];
    entities: string[];
    filters: Record<string, string>;
}

const ACTION_COLORS: Record<string, string> = {
    created: 'bg-green-100 text-green-700',
    updated: 'bg-blue-100 text-blue-700',
    deleted: 'bg-red-100 text-red-700',
    restored: 'bg-amber-100 text-amber-700',
};

export default function AuditIndex({ logs, users, entities, filters }: Props) {
    const [form, setForm] = useState(filters);

    function applyFilter(e: React.FormEvent) {
        e.preventDefault();
        router.get('/auditoria', form, { preserveState: true, replace: true });
    }

    function clearFilters() {
        setForm({});
        router.get('/auditoria', {}, { preserveState: true, replace: true });
    }

    return (
        <AppLayout>
            <Head title="Auditoria" />
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Auditoria</h1>
                    <p className="mt-1 text-sm text-gray-500">Registro de ações realizadas no sistema</p>
                </div>

                {/* Filtros */}
                <form onSubmit={applyFilter} className="rounded-xl bg-white p-4 shadow-sm border border-gray-100">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        <select
                            value={form.user_id ?? ''}
                            onChange={(e) => setForm({ ...form, user_id: e.target.value })}
                            className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        >
                            <option value="">Todos os usuários</option>
                            {users.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
                        </select>

                        <select
                            value={form.entity ?? ''}
                            onChange={(e) => setForm({ ...form, entity: e.target.value })}
                            className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        >
                            <option value="">Todos os tipos</option>
                            {entities.map((e) => <option key={e} value={e}>{e}</option>)}
                        </select>

                        <select
                            value={form.action ?? ''}
                            onChange={(e) => setForm({ ...form, action: e.target.value })}
                            className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        >
                            <option value="">Todas as ações</option>
                            <option value="created">Criado</option>
                            <option value="updated">Atualizado</option>
                            <option value="deleted">Excluído</option>
                        </select>

                        <input
                            type="date"
                            value={form.date_from ?? ''}
                            onChange={(e) => setForm({ ...form, date_from: e.target.value })}
                            className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                        <input
                            type="date"
                            value={form.date_to ?? ''}
                            onChange={(e) => setForm({ ...form, date_to: e.target.value })}
                            className="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                    </div>
                    <div className="flex gap-2 mt-3">
                        <button type="submit" className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                            Filtrar
                        </button>
                        <button type="button" onClick={clearFilters} className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                            Limpar
                        </button>
                    </div>
                </form>

                {/* Tabela */}
                <div className="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
                    <div className="px-4 py-3 border-b border-gray-100">
                        <p className="text-sm text-gray-500">{logs.total} registro{logs.total !== 1 ? 's' : ''}</p>
                    </div>
                    <table className="min-w-full divide-y divide-gray-100">
                        <thead>
                            <tr className="bg-gray-50">
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Data / Hora</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Usuário</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Ação</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Entidade</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">IP</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {logs.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-4 py-12 text-center text-sm text-gray-500">Nenhum registro encontrado.</td>
                                </tr>
                            )}
                            {logs.data.map((log) => (
                                <tr key={log.id} className="hover:bg-gray-50 transition-colors">
                                    <td className="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                        {new Date(log.created_at).toLocaleString('pt-BR')}
                                    </td>
                                    <td className="px-4 py-3">
                                        {log.user ? (
                                            <div>
                                                <p className="text-sm font-medium text-gray-900">{log.user.name}</p>
                                                <p className="text-xs text-gray-500">{log.user.email}</p>
                                            </div>
                                        ) : (
                                            <span className="text-xs text-gray-400">Sistema</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${ACTION_COLORS[log.action] ?? 'bg-gray-100 text-gray-700'}`}>
                                            {log.action}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-700">
                                        <span className="font-medium">{log.entity}</span>
                                        {log.entity_id && <span className="ml-1 text-xs text-gray-400">#{log.entity_id.slice(0, 8)}</span>}
                                    </td>
                                    <td className="px-4 py-3 text-xs text-gray-500">{log.ip_address ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {logs.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-gray-500">
                            Mostrando {(logs.current_page - 1) * logs.per_page + 1}–{Math.min(logs.current_page * logs.per_page, logs.total)} de {logs.total}
                        </p>
                        <div className="flex gap-1">
                            {logs.links.map((link, i) => (
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
