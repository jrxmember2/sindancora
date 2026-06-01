import { Head, useForm, Link } from '@inertiajs/react';
import { useState } from 'react';
import { CheckCircle } from 'lucide-react';

interface Plan {
    id: string;
    name: string;
    display_name: string;
    description: string;
    price_monthly: string;
}

interface Props {
    plans: Plan[];
}

export default function OnboardingRegister({ plans }: Props) {
    const [step, setStep] = useState(1);

    const { data, setData, post, processing, errors } = useForm({
        company_name: '',
        document: '',
        email: '',
        phone: '',
        plan_id: plans[0]?.id ?? '',
        admin_name: '',
        admin_email: '',
        admin_password: '',
        admin_password_confirmation: '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/cadastro');
    }

    function Field({ label, name, type = 'text', required = false }: {
        label: string; name: keyof typeof data; type?: string; required?: boolean;
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
                    className="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
                {errors[name] && <p className="mt-1 text-xs text-red-600">{errors[name]}</p>}
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gradient-to-br from-blue-50 to-white flex items-center justify-center p-4">
            <Head title="Criar conta — SindÂncora" />

            <div className="w-full max-w-2xl">
                {/* Header */}
                <div className="text-center mb-8">
                    <div className="inline-flex items-center justify-center h-12 w-12 rounded-xl bg-blue-600 text-white text-xl font-bold mb-4">S</div>
                    <h1 className="text-3xl font-bold text-gray-900">Crie sua conta</h1>
                    <p className="mt-2 text-gray-500">Gestão condominial profissional, simples e eficiente</p>
                </div>

                {/* Steps */}
                <div className="flex items-center justify-center gap-4 mb-8">
                    {['Empresa', 'Plano', 'Acesso'].map((label, i) => (
                        <div key={label} className="flex items-center gap-2">
                            <div className={`flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold ${step > i + 1 ? 'bg-green-500 text-white' : step === i + 1 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500'}`}>
                                {step > i + 1 ? <CheckCircle className="h-4 w-4" /> : i + 1}
                            </div>
                            <span className={`text-sm font-medium ${step === i + 1 ? 'text-blue-600' : 'text-gray-400'}`}>{label}</span>
                            {i < 2 && <div className="w-8 h-px bg-gray-200 ml-2" />}
                        </div>
                    ))}
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="rounded-2xl bg-white shadow-sm border border-gray-100 p-8 space-y-5">
                        {/* Step 1: Empresa */}
                        {step === 1 && (
                            <>
                                <h2 className="text-lg font-semibold text-gray-900">Dados da empresa</h2>
                                <Field label="Nome do condomínio ou administradora" name="company_name" required />
                                <Field label="CNPJ" name="document" />
                                <div className="grid grid-cols-2 gap-4">
                                    <Field label="E-mail corporativo" name="email" type="email" required />
                                    <Field label="Telefone" name="phone" />
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setStep(2)}
                                    disabled={!data.company_name || !data.email}
                                    className="w-full rounded-lg bg-blue-600 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50 transition-colors"
                                >
                                    Continuar
                                </button>
                            </>
                        )}

                        {/* Step 2: Plano */}
                        {step === 2 && (
                            <>
                                <h2 className="text-lg font-semibold text-gray-900">Escolha seu plano</h2>
                                <div className="space-y-3">
                                    {plans.map((plan) => (
                                        <label key={plan.id} className={`flex items-center gap-4 rounded-xl border-2 p-4 cursor-pointer transition-colors ${data.plan_id === plan.id ? 'border-blue-600 bg-blue-50' : 'border-gray-200 hover:border-gray-300'}`}>
                                            <input
                                                type="radio"
                                                name="plan_id"
                                                value={plan.id}
                                                checked={data.plan_id === plan.id}
                                                onChange={() => setData('plan_id', plan.id)}
                                                className="sr-only"
                                            />
                                            <div className="flex-1">
                                                <p className="font-semibold text-gray-900">{plan.display_name}</p>
                                                {plan.description && <p className="text-xs text-gray-500 mt-0.5">{plan.description}</p>}
                                            </div>
                                            <div className="text-right">
                                                <p className="text-lg font-bold text-gray-900">
                                                    R$ {parseFloat(plan.price_monthly).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                                                </p>
                                                <p className="text-xs text-gray-500">por mês</p>
                                            </div>
                                        </label>
                                    ))}
                                </div>
                                <div className="flex gap-3 pt-2">
                                    <button type="button" onClick={() => setStep(1)} className="flex-1 rounded-lg border border-gray-300 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        Voltar
                                    </button>
                                    <button type="button" onClick={() => setStep(3)} className="flex-1 rounded-lg bg-blue-600 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                                        Continuar
                                    </button>
                                </div>
                            </>
                        )}

                        {/* Step 3: Acesso */}
                        {step === 3 && (
                            <>
                                <h2 className="text-lg font-semibold text-gray-900">Dados de acesso</h2>
                                <Field label="Seu nome completo" name="admin_name" required />
                                <Field label="Seu e-mail (será usado para login)" name="admin_email" type="email" required />
                                <div className="grid grid-cols-2 gap-4">
                                    <Field label="Senha" name="admin_password" type="password" required />
                                    <Field label="Confirmar senha" name="admin_password_confirmation" type="password" required />
                                </div>
                                <p className="text-xs text-gray-500">Ao criar sua conta, você concorda com os Termos de Uso e Política de Privacidade.</p>
                                <div className="flex gap-3 pt-2">
                                    <button type="button" onClick={() => setStep(2)} className="flex-1 rounded-lg border border-gray-300 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        Voltar
                                    </button>
                                    <button type="submit" disabled={processing} className="flex-1 rounded-lg bg-blue-600 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                                        {processing ? 'Criando conta...' : 'Criar conta grátis'}
                                    </button>
                                </div>
                            </>
                        )}
                    </div>
                </form>

                <p className="text-center mt-6 text-sm text-gray-500">
                    Já tem uma conta?{' '}
                    <Link href="/login" className="text-blue-600 hover:text-blue-800 font-medium">Fazer login</Link>
                </p>
            </div>
        </div>
    );
}
