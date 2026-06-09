import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { Wallet, ShipWheel, DoorClosed, BarChart3, ShieldCheck, Eye, EyeOff, Loader2 } from 'lucide-react';

const highlights = [
    { icon: Wallet, title: 'Financeiro completo', text: 'Cobranças, boletos e PIX com conciliação automática.' },
    { icon: ShipWheel, title: 'LemeIA', text: 'Assistente que responde com base nos documentos do condomínio.' },
    { icon: DoorClosed, title: 'Portaria digital', text: 'Controle de visitantes, encomendas e QR por condomínio.' },
    { icon: BarChart3, title: 'Visão consolidada', text: 'Relatórios e cronograma de toda a sua carteira.' },
];

export default function Login({ status, canResetPassword }: { status?: string; canResetPassword: boolean }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: true as boolean,
    });
    const [showPassword, setShowPassword] = useState(false);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <>
            <Head title="Entrar" />

            <div className="flex min-h-screen bg-white">
                {/* Lado esquerdo — marca + venda */}
                <div className="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-gradient-to-br from-blue-700 via-blue-800 to-indigo-900 p-12 text-white lg:flex">
                    {/* brilhos decorativos */}
                    <div className="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-blue-400/20 blur-3xl" />
                    <div className="pointer-events-none absolute -bottom-32 -left-16 h-96 w-96 rounded-full bg-indigo-400/20 blur-3xl" />

                    <div className="relative">
                        <div className="inline-flex items-center rounded-2xl bg-white/95 px-5 py-3 shadow-lg">
                            <img src="/brand/logo.svg" alt="SindÂncora" className="h-9 w-auto" />
                        </div>
                    </div>

                    <div className="relative max-w-md">
                        <h1 className="text-4xl font-bold leading-tight">
                            A gestão do seu condomínio, sob controle.
                        </h1>
                        <p className="mt-4 text-lg text-blue-100">
                            Financeiro, comunicação, operação e inteligência artificial em uma única plataforma — feita para síndicos e administradoras.
                        </p>

                        <ul className="mt-10 space-y-5">
                            {highlights.map(({ icon: Icon, title, text }) => (
                                <li key={title} className="flex items-start gap-3">
                                    <span className="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/20">
                                        <Icon className="h-5 w-5" />
                                    </span>
                                    <div>
                                        <p className="font-semibold">{title}</p>
                                        <p className="text-sm text-blue-100">{text}</p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div className="relative flex items-center gap-2 text-sm text-blue-100">
                        <ShieldCheck className="h-4 w-4" />
                        Dados isolados por cliente e criptografados.
                    </div>
                </div>

                {/* Lado direito — formulário */}
                <div className="flex w-full items-center justify-center px-6 py-12 lg:w-1/2">
                    <div className="w-full max-w-sm">
                        <div className="mb-8 lg:hidden">
                            <img src="/brand/logo.svg" alt="SindÂncora" className="h-10 w-auto" />
                        </div>

                        <h2 className="text-2xl font-bold text-gray-900">Bem-vindo de volta</h2>
                        <p className="mt-1 text-sm text-gray-500">Entre para acessar o painel do seu condomínio.</p>

                        {status && (
                            <div className="mt-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                                {status}
                            </div>
                        )}

                        <form onSubmit={submit} className="mt-8 space-y-5">
                            <div>
                                <label htmlFor="email" className="mb-1.5 block text-sm font-medium text-gray-700">E-mail</label>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    autoComplete="username"
                                    autoFocus
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="voce@email.com"
                                />
                                {errors.email && <p className="mt-1.5 text-xs text-red-600">{errors.email}</p>}
                            </div>

                            <div>
                                <label htmlFor="password" className="mb-1.5 block text-sm font-medium text-gray-700">Senha</label>
                                <div className="relative">
                                    <input
                                        id="password"
                                        type={showPassword ? 'text' : 'password'}
                                        name="password"
                                        value={data.password}
                                        autoComplete="current-password"
                                        onChange={(e) => setData('password', e.target.value)}
                                        className="block w-full rounded-lg border-gray-300 pr-10 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        placeholder="Sua senha"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword((v) => !v)}
                                        className="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
                                        tabIndex={-1}
                                        aria-label={showPassword ? 'Ocultar senha' : 'Mostrar senha'}
                                    >
                                        {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                    </button>
                                </div>
                                {errors.password && <p className="mt-1.5 text-xs text-red-600">{errors.password}</p>}
                            </div>

                            <div className="flex items-center justify-between">
                                <label className="flex items-center gap-2 text-sm text-gray-600">
                                    <input
                                        type="checkbox"
                                        checked={data.remember}
                                        onChange={(e) => setData('remember', e.target.checked)}
                                        className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    Manter conectado por 12 horas
                                </label>
                                {canResetPassword && (
                                    <Link href={route('password.request')} className="text-sm font-medium text-blue-600 hover:text-blue-700">
                                        Esqueceu a senha?
                                    </Link>
                                )}
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-60"
                            >
                                {processing && <Loader2 className="h-4 w-4 animate-spin" />}
                                {processing ? 'Entrando...' : 'Entrar'}
                            </button>
                        </form>

                        <p className="mt-8 text-center text-xs text-gray-400">
                            © {new Date().getFullYear()} SindÂncora · Gestão condominial
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}
