import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { Shield, Lock, ChevronDown, ChevronUp, Pencil, X, Save } from 'lucide-react';
import { useState } from 'react';

interface Role {
    id: string;
    name: string;
    display_name: string;
    description: string | null;
    is_system: boolean;
    tenant_id: string | null;
    permission_ids: string[];
    permissions_by_module: Record<string, string[]>;
    permissions_count: number;
}

interface PermissionItem {
    id: string;
    action: string;
    name: string;
}

interface Props {
    roles: Role[];
    allPermissions: Record<string, PermissionItem[]>;
}

const MODULE_LABELS: Record<string, string> = {
    users: 'Usuários',
    condominiums: 'Condomínios',
    units: 'Unidades',
    residents: 'Moradores',
    announcements: 'Comunicados',
    occurrences: 'Ocorrências',
    reservations: 'Reservas',
    documents: 'Documentos',
    financial: 'Financeiro',
    reports: 'Relatórios',
    settings: 'Configurações',
    audit: 'Auditoria',
};

function EditModal({
    role,
    allPermissions,
    onClose,
}: {
    role: Role;
    allPermissions: Record<string, PermissionItem[]>;
    onClose: () => void;
}) {
    const [selected, setSelected] = useState<Set<string>>(new Set(role.permission_ids));
    const [processing, setProcessing] = useState(false);

    const toggle = (id: string) => {
        setSelected((prev) => {
            const next = new Set(prev);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });
    };

    const toggleModule = (perms: PermissionItem[]) => {
        const allSelected = perms.every((p) => selected.has(p.id));
        setSelected((prev) => {
            const next = new Set(prev);
            perms.forEach((p) => (allSelected ? next.delete(p.id) : next.add(p.id)));
            return next;
        });
    };

    const save = () => {
        setProcessing(true);
        router.patch(
            route('roles.update', role.id),
            { permission_ids: Array.from(selected) },
            {
                onSuccess: onClose,
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-2xl rounded-2xl bg-white shadow-xl flex flex-col max-h-[90vh]">
                {/* Header */}
                <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <div>
                        <h2 className="text-base font-semibold text-gray-900">Editar permissões</h2>
                        <p className="text-sm text-gray-500">{role.display_name}</p>
                    </div>
                    <button onClick={onClose} className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                        <X className="h-5 w-5" />
                    </button>
                </div>

                {/* Body */}
                <div className="overflow-y-auto px-6 py-4 space-y-5 flex-1">
                    {Object.entries(allPermissions).map(([module, perms]) => {
                        const allSelected = perms.every((p) => selected.has(p.id));
                        const someSelected = perms.some((p) => selected.has(p.id));
                        return (
                            <div key={module}>
                                <div className="flex items-center gap-2 mb-2">
                                    <input
                                        type="checkbox"
                                        checked={allSelected}
                                        ref={(el) => { if (el) el.indeterminate = !allSelected && someSelected; }}
                                        onChange={() => toggleModule(perms)}
                                        className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span className="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        {MODULE_LABELS[module] ?? module}
                                    </span>
                                </div>
                                <div className="flex flex-wrap gap-2 pl-6">
                                    {perms.map((perm) => (
                                        <label key={perm.id} className="flex items-center gap-1.5 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={selected.has(perm.id)}
                                                onChange={() => toggle(perm.id)}
                                                className="h-3.5 w-3.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            />
                                            <span className="text-xs text-gray-700">{perm.action}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Footer */}
                <div className="flex items-center justify-between border-t border-gray-100 px-6 py-4">
                    <span className="text-sm text-gray-500">{selected.size} permissões selecionadas</span>
                    <div className="flex gap-2">
                        <button
                            onClick={onClose}
                            className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Cancelar
                        </button>
                        <button
                            onClick={save}
                            disabled={processing}
                            className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors"
                        >
                            <Save className="h-4 w-4" />
                            {processing ? 'Salvando…' : 'Salvar'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function RolesIndex({ roles, allPermissions }: Props) {
    const [expanded, setExpanded] = useState<string | null>(null);
    const [editing, setEditing] = useState<Role | null>(null);

    return (
        <AppLayout>
            <Head title="Perfis e Permissões" />

            {editing && (
                <EditModal
                    role={editing}
                    allPermissions={allPermissions}
                    onClose={() => setEditing(null)}
                />
            )}

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Perfis e Permissões</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Perfis do sistema <Lock className="inline h-3.5 w-3.5 text-gray-400" /> são imutáveis. Perfis personalizados podem ter permissões editadas.
                    </p>
                </div>

                <div className="grid gap-3">
                    {roles.map((role) => (
                        <div key={role.id} className="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
                            <div className="flex items-center gap-4 px-6 py-4">
                                <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${role.is_system ? 'bg-blue-100' : 'bg-violet-100'}`}>
                                    <Shield className={`h-5 w-5 ${role.is_system ? 'text-blue-600' : 'text-violet-600'}`} />
                                </div>

                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2">
                                        <p className="text-sm font-semibold text-gray-900">{role.display_name}</p>
                                        {role.is_system && <Lock className="h-3.5 w-3.5 text-gray-400" />}
                                    </div>
                                    {role.description && (
                                        <p className="text-xs text-gray-500 mt-0.5 truncate">{role.description}</p>
                                    )}
                                </div>

                                <div className="flex items-center gap-3 shrink-0">
                                    <div className="text-right">
                                        <span className="text-sm font-medium text-gray-700">{role.permissions_count}</span>
                                        <span className="text-xs text-gray-400 ml-1">permissões</span>
                                    </div>

                                    {!role.is_system && role.tenant_id && (
                                        <button
                                            onClick={() => setEditing(role)}
                                            className="flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 hover:text-blue-600 hover:border-blue-200 transition-colors"
                                        >
                                            <Pencil className="h-3.5 w-3.5" />
                                            Editar
                                        </button>
                                    )}

                                    <button
                                        onClick={() => setExpanded(expanded === role.id ? null : role.id)}
                                        className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
                                    >
                                        {expanded === role.id ? (
                                            <ChevronUp className="h-4 w-4" />
                                        ) : (
                                            <ChevronDown className="h-4 w-4" />
                                        )}
                                    </button>
                                </div>
                            </div>

                            {expanded === role.id && (
                                <div className="border-t border-gray-100 px-6 py-4">
                                    {Object.keys(role.permissions_by_module).length === 0 ? (
                                        <p className="text-sm text-gray-500">Sem permissões configuradas.</p>
                                    ) : (
                                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                            {Object.entries(role.permissions_by_module).map(([module, actions]) => (
                                                <div key={module}>
                                                    <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                                        {MODULE_LABELS[module] ?? module}
                                                    </p>
                                                    <div className="flex flex-wrap gap-1">
                                                        {(actions as string[]).map((action) => (
                                                            <span
                                                                key={action}
                                                                className="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700"
                                                            >
                                                                {action}
                                                            </span>
                                                        ))}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
