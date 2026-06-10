import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { ShipWheel, MessagesSquare, Hammer, BarChart3, Eye, EyeOff, Loader2, ArrowRight } from 'lucide-react';

const highlights = [
    { icon: ShipWheel, title: 'LemeIA', text: 'Respostas com base nos documentos do seu condomínio.' },
    { icon: MessagesSquare, title: 'Atendimento', text: 'WhatsApp centralizado com seus moradores.' },
    { icon: Hammer, title: 'Obras & reformas', text: 'Orçamentos, cronograma e andamento no controle.' },
    { icon: BarChart3, title: 'Sua carteira', text: 'Visão consolidada de todos os condomínios.' },
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

            <div className="flex min-h-screen bg-slate-50">
                {/* ===== Lado esquerdo — vitrine ===== */}
                <div className="relative hidden overflow-hidden bg-gradient-to-br from-blue-700 via-indigo-800 to-blue-950 bg-[length:200%_200%] p-12 text-white animate-gradient-shift lg:flex lg:w-3/5 lg:flex-col lg:justify-between xl:p-16">
                    {/* leme gigante girando ao fundo */}
                    <ShipWheel className="pointer-events-none absolute -right-24 top-1/2 h-[640px] w-[640px] -translate-y-1/2 text-white/5 animate-spin-slow" strokeWidth={0.6} />
                    {/* blobs animados */}
                    <div className="pointer-events-none absolute -left-24 -top-24 h-96 w-96 rounded-full bg-sky-400/25 blur-3xl animate-blob" />
                    <div className="pointer-events-none absolute bottom-0 right-1/4 h-80 w-80 rounded-full bg-indigo-400/25 blur-3xl animate-blob" style={{ animationDelay: '3s' }} />
                    <div className="pointer-events-none absolute -bottom-24 -left-10 h-72 w-72 rounded-full bg-cyan-400/20 blur-3xl animate-blob" style={{ animationDelay: '6s' }} />

                    {/* topo: marca */}
                    <div className="relative flex items-center gap-2 animate-fade-up">
                        <ShipWheel className="h-7 w-7" />
                        <span className="text-lg font-semibold tracking-tight">SindÂncora</span>
                    </div>

                    {/* meio: pitch + cards */}
                    <div className="relative max-w-xl">
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-blue-100 ring-1 ring-white/20 animate-fade-up">
                            <ShipWheel className="h-3.5 w-3.5" /> O copiloto do síndico
                        </span>
                        <h1 className="mt-5 text-4xl font-bold leading-tight animate-fade-up xl:text-5xl" style={{ animationDelay: '0.05s' }}>
                            Comande a gestão dos seus condomínios.
                        </h1>
                        <p className="mt-4 max-w-md text-lg text-blue-100 animate-fade-up" style={{ animationDelay: '0.1s' }}>
                            Atendimento, obras, comunicação e decisões — tudo em um só lugar, com a LemeIA ao seu lado.
                        </p>

                        <div className="mt-10 grid grid-cols-2 gap-4">
                            {highlights.map(({ icon: Icon, title, text }, i) => (
                                <div
                                    key={title}
                                    className="group rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-md transition hover:bg-white/15 animate-floaty"
                                    style={{ animationDelay: `${i * 0.8}s` }}
                                >
                                    <span className="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-white/25 to-white/5 ring-1 ring-white/20">
                                        <Icon className="h-6 w-6" strokeWidth={2} />
                                    </span>
                                    <p className="mt-3 font-semibold">{title}</p>
                                    <p className="mt-0.5 text-sm leading-snug text-blue-100">{text}</p>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* rodapé: powered by serratech */}
                    <a
                        href="https://www.serratech.tec.br"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="relative flex w-fit items-center gap-2.5 opacity-80 transition hover:opacity-100"
                    >
                        <span className="flex items-center rounded-md bg-white px-2 py-1 shadow-sm">
                            <img src="/brand/serratech.svg" alt="Serratech" className="h-4 w-auto" />
                        </span>
                        <span className="text-sm text-blue-100">Powered by Serratech</span>
                    </a>
                </div>

                {/* ===== Lado direito — formulário ===== */}
                <div className="relative flex w-full items-center justify-center px-6 py-12 lg:w-2/5">
                    {/* leve textura no fundo do form */}
                    <div className="pointer-events-none absolute right-0 top-0 h-72 w-72 rounded-full bg-blue-100/60 blur-3xl" />

                    <div className="relative w-full max-w-sm animate-fade-up">
                        {/* logo acima do título */}
                        <div className="mb-6 flex justify-center">
                            <img src="/brand/logo.svg" alt="SindÂncora" className="h-24 w-auto drop-shadow-sm" />
                        </div>

                        <div className="text-center">
                            <h2 className="text-2xl font-bold text-slate-900">Bem-vindo de volta</h2>
                            <p className="mt-1 text-sm text-slate-500">Entre para acessar o painel do seu condomínio.</p>
                        </div>

                        {status && (
                            <div className="mt-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                                {status}
                            </div>
                        )}

                        <form onSubmit={submit} className="mt-8 space-y-5">
                            <div>
                                <label htmlFor="email" className="mb-1.5 block text-sm font-medium text-slate-700">E-mail</label>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    autoComplete="username"
                                    autoFocus
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="block w-full rounded-lg border-slate-300 text-sm shadow-sm transition focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="voce@email.com"
                                />
                                {errors.email && <p className="mt-1.5 text-xs text-red-600">{errors.email}</p>}
                            </div>

                            <div>
                                <label htmlFor="password" className="mb-1.5 block text-sm font-medium text-slate-700">Senha</label>
                                <div className="relative">
                                    <input
                                        id="password"
                                        type={showPassword ? 'text' : 'password'}
                                        name="password"
                                        value={data.password}
                                        autoComplete="current-password"
                                        onChange={(e) => setData('password', e.target.value)}
                                        className="block w-full rounded-lg border-slate-300 pr-10 text-sm shadow-sm transition focus:border-blue-500 focus:ring-blue-500"
                                        placeholder="Sua senha"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword((v) => !v)}
                                        className="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600"
                                        tabIndex={-1}
                                        aria-label={showPassword ? 'Ocultar senha' : 'Mostrar senha'}
                                    >
                                        {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                    </button>
                                </div>
                                {errors.password && <p className="mt-1.5 text-xs text-red-600">{errors.password}</p>}
                            </div>

                            <div className="flex items-center justify-between">
                                <label className="flex items-center gap-2 text-sm text-slate-600">
                                    <input
                                        type="checkbox"
                                        checked={data.remember}
                                        onChange={(e) => setData('remember', e.target.checked)}
                                        className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
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
                                className="group flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:from-blue-700 hover:to-indigo-700 disabled:opacity-60"
                            >
                                {processing ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                                {processing ? 'Entrando...' : 'Entrar'}
                                {!processing && <ArrowRight className="h-4 w-4 transition group-hover:translate-x-0.5" />}
                            </button>
                        </form>

                        {/* serratech também no mobile (canto do form) */}
                        <a
                            href="https://www.serratech.tec.br"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="mt-10 flex items-center justify-center gap-2 text-xs text-slate-400 transition hover:text-slate-600 lg:hidden"
                        >
                            <img src="/brand/serratech.svg" alt="Serratech" className="h-3.5 w-auto" />
                            Powered by Serratech
                        </a>
                    </div>
                </div>
            </div>
        </>
    );
}
