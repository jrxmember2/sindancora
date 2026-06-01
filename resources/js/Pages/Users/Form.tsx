import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface Role { id: string; name: string; display_name: string }
interface UserData {
    id?: string;
    name: string;
    email: string;
    phone?: string;
    status: string;
    user_roles?: { role: { id: string } }[];
}

interface Props {
    user?: UserData;
    roles: Role[];
    isEdit?: boolean;
}

export default function UserForm({ user, roles, isEdit = false }: Props) {
    const currentRoleId = user?.user_roles?.[0]?.role?.id ?? '';

    const { data, setData, post, put, processing, errors } = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        phone: user?.phone ?? '',
        password: '',
        status: user?.status ?? 'active',
        role_id: currentRoleId,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (isEdit && user?.id) {
            put(`/usuarios/${user.id}`);
        } else {
            post('/usuarios');
        }
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
        <AppLayout>
            <Head title={isEdit ? `Editar — ${user?.name}` : 'Novo Usuário'} />
            <div className="max-w-xl space-y-6">
                <div className="flex items-center gap-4">
                    <Link href="/usuarios" className="text-gray-400 hover:text-gray-600">
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <h1 className="text-2xl font-bold text-gray-900">{isEdit ? 'Editar Usuário' : 'Novo Usuário'}</h1>
                </div>

                <form onSubmit={handleSubmit} className="rounded-xl bg-white p-6 shadow-sm border border-gray-100 space-y-4">
                    <Field label="Nome completo" name="name" required />
                    <Field label="E-mail" name="email" type="email" required />
                    <Field label="Telefone" name="phone" />
                    <Field
                        label={isEdit ? 'Nova senha (deixe em branco para manter)' : 'Senha'}
                        name="password"
                        type="password"
                        required={!isEdit}
                    />

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Perfil (role)</label>
                        <select
                            value={data.role_id}
                            onChange={(e) => setData('role_id', e.target.value)}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        >
                            <option value="">Sem perfil</option>
                            {roles.map((role) => (
                                <option key={role.id} value={role.id}>{role.display_name}</option>
                            ))}
                        </select>
                        {errors.role_id && <p className="mt-1 text-xs text-red-600">{errors.role_id}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select
                            value={data.status}
                            onChange={(e) => setData('status', e.target.value)}
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        >
                            <option value="active">Ativo</option>
                            <option value="inactive">Inativo</option>
                        </select>
                    </div>

                    <div className="flex items-center gap-3 pt-2">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors"
                        >
                            {processing ? 'Salvando...' : isEdit ? 'Salvar alterações' : 'Criar usuário'}
                        </button>
                        <Link href="/usuarios" className="text-sm text-gray-600 hover:text-gray-900">Cancelar</Link>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
