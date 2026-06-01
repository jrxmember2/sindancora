import AppLayout from '@/Layouts/AppLayout';
import { isValidEmail, maskPhone } from '@/lib/masks';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';

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

const inputClass =
    'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';

// Definido em nível de módulo (fora do componente) para não remontar a cada tecla — o que
// fazia o input perder o foco a cada caractere digitado.
function Field({
    label, value, onChange, type = 'text', required = false, hint, error, placeholder, rightSlot,
}: {
    label: string;
    value: string;
    onChange: (v: string) => void;
    type?: string;
    required?: boolean;
    hint?: string;
    error?: string;
    placeholder?: string;
    rightSlot?: React.ReactNode;
}) {
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
                {label} {required && <span className="text-red-500">*</span>}
            </label>
            <div className="relative">
                <input
                    type={type}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={placeholder}
                    className={inputClass + (rightSlot ? ' pr-10' : '')}
                />
                {rightSlot}
            </div>
            {hint && <p className="mt-1 text-xs text-gray-500">{hint}</p>}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

export default function UserForm({ user, roles, isEdit = false }: Props) {
    const currentRoleId = user?.user_roles?.[0]?.role?.id ?? '';
    const [showPassword, setShowPassword] = useState(false);

    const { data, setData, post, put, processing, errors } = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        phone: user?.phone ?? '',
        password: '',
        status: user?.status ?? 'active',
        role_id: currentRoleId,
    });

    const emailInvalid = data.email.length > 0 && !isValidEmail(data.email);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (isEdit && user?.id) {
            put(`/usuarios/${user.id}`);
        } else {
            post('/usuarios');
        }
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
                    <Field label="Nome completo" required value={data.name} onChange={(v) => setData('name', v)} error={errors.name} />
                    <Field
                        label="E-mail"
                        type="email"
                        required
                        value={data.email}
                        onChange={(v) => setData('email', v)}
                        error={errors.email ?? (emailInvalid ? 'E-mail inválido (precisa conter @ e domínio).' : undefined)}
                    />
                    <Field
                        label="Telefone"
                        value={maskPhone(data.phone)}
                        onChange={(v) => setData('phone', maskPhone(v))}
                        error={errors.phone}
                        placeholder="(00) 00000-0000"
                    />
                    <Field
                        label={isEdit ? 'Nova senha (deixe em branco para manter)' : 'Senha'}
                        type={showPassword ? 'text' : 'password'}
                        required={!isEdit}
                        value={data.password}
                        onChange={(v) => setData('password', v)}
                        error={errors.password}
                        rightSlot={
                            <button
                                type="button"
                                onClick={() => setShowPassword((s) => !s)}
                                className="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
                                tabIndex={-1}
                                aria-label={showPassword ? 'Ocultar senha' : 'Mostrar senha'}
                            >
                                {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                            </button>
                        }
                    />

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Perfil (role)</label>
                        <select
                            value={data.role_id}
                            onChange={(e) => setData('role_id', e.target.value)}
                            className={inputClass}
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
                            className={inputClass}
                        >
                            <option value="active">Ativo</option>
                            <option value="inactive">Inativo</option>
                        </select>
                    </div>

                    <div className="flex items-center gap-3 pt-2">
                        <button
                            type="submit"
                            disabled={processing || emailInvalid}
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
