import PortalLayout from '@/Layouts/PortalLayout';
import { Head, useForm } from '@inertiajs/react';

interface Props {
    profile: { name: string; email: string | null; phone: string | null };
}

export default function PortalProfileEdit({ profile }: Props) {
    const { data, setData, patch, processing, errors, reset } = useForm({
        name: profile.name,
        phone: profile.phone ?? '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(route('portal.profile.update'), {
            preserveScroll: true,
            onSuccess: () => reset('password', 'password_confirmation'),
        });
    };

    const field = 'mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';

    return (
        <PortalLayout title="Meu perfil">
            <Head title="Meu perfil" />

            <form onSubmit={submit} className="max-w-lg space-y-5">
                <div className="space-y-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 className="text-sm font-semibold text-gray-900">Dados de contato</h2>

                    <div>
                        <label className="block text-sm font-medium text-gray-700">Nome *</label>
                        <input value={data.name} onChange={(e) => setData('name', e.target.value)} className={field} />
                        {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700">E-mail</label>
                        <input value={profile.email ?? ''} readOnly className={`${field} bg-gray-50 text-gray-500`} />
                        <p className="mt-1 text-xs text-gray-400">O e-mail é usado para login e não pode ser alterado aqui.</p>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700">Telefone</label>
                        <input value={data.phone} onChange={(e) => setData('phone', e.target.value)} className={field} />
                        {errors.phone && <p className="mt-1 text-xs text-red-600">{errors.phone}</p>}
                    </div>
                </div>

                <div className="space-y-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 className="text-sm font-semibold text-gray-900">Alterar senha</h2>
                    <p className="text-xs text-gray-400">Deixe em branco para manter a senha atual.</p>

                    <div>
                        <label className="block text-sm font-medium text-gray-700">Nova senha</label>
                        <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} autoComplete="new-password" className={field} />
                        {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700">Confirmar nova senha</label>
                        <input type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} autoComplete="new-password" className={field} />
                    </div>
                </div>

                <div className="flex justify-end">
                    <button type="submit" disabled={processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        {processing ? 'Salvando…' : 'Salvar alterações'}
                    </button>
                </div>
            </form>
        </PortalLayout>
    );
}
