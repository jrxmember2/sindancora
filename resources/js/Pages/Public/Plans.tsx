import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Check, Loader2 } from 'lucide-react';

interface Plan {
    id: string;
    name: string;
    display_name: string;
    description: string | null;
    price_monthly: string | null;
    price_yearly: string | null;
}

interface Props {
    plans: Plan[];
    errors: Record<string, string>;
}

const brl = (v: string | number | null) =>
    v == null ? '—' : `R$ ${parseFloat(String(v)).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;

export default function Plans({ plans }: Props) {
    const [cycle, setCycle] = useState<'monthly' | 'yearly'>('monthly');
    const [selected, setSelected] = useState<Plan | null>(null);

    const form = useForm({
        plan_id: '',
        billing_cycle: 'monthly',
        billing_type: 'PIX',
        company_name: '',
        document: '',
        email: '',
        phone: '',
        admin_name: '',
    });

    const choose = (plan: Plan) => {
        setSelected(plan);
        form.setData((d) => ({ ...d, plan_id: plan.id, billing_cycle: cycle }));
        setTimeout(() => document.getElementById('checkout-form')?.scrollIntoView({ behavior: 'smooth' }), 50);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/checkout');
    };

    const price = (p: Plan) => (cycle === 'yearly' ? p.price_yearly : p.price_monthly);

    return (
        <div className="min-h-screen bg-gradient-to-b from-gray-50 to-white">
            <Head title="Planos — Sindâncora" />

            <header className="mx-auto flex max-w-6xl items-center gap-2.5 px-6 py-6">
                <span className="flex items-center rounded-lg bg-gray-900 px-1.5 py-1">
                    <img src="/brand/logo.svg" alt="SindÂncora" className="h-6 w-auto" />
                </span>
                <span className="text-lg font-bold text-gray-900">SindÂncora</span>
            </header>

            <main className="mx-auto max-w-6xl px-6 pb-20">
                <div className="text-center">
                    <h1 className="text-3xl font-bold text-gray-900 sm:text-4xl">Escolha seu plano</h1>
                    <p className="mt-2 text-gray-500">Gestão condominial completa. Cancele quando quiser.</p>

                    <div className="mt-6 inline-flex rounded-lg border border-gray-200 bg-white p-1">
                        {(['monthly', 'yearly'] as const).map((c) => (
                            <button
                                key={c}
                                onClick={() => { setCycle(c); form.setData('billing_cycle', c); }}
                                className={`rounded-md px-4 py-1.5 text-sm font-medium transition ${cycle === c ? 'bg-blue-600 text-white' : 'text-gray-600'}`}
                            >
                                {c === 'monthly' ? 'Mensal' : 'Anual'}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    {plans.map((plan) => (
                        <div
                            key={plan.id}
                            className={`flex flex-col rounded-2xl border bg-white p-6 shadow-sm transition ${selected?.id === plan.id ? 'border-blue-500 ring-2 ring-blue-100' : 'border-gray-100'}`}
                        >
                            <h3 className="text-lg font-semibold text-gray-900">{plan.display_name}</h3>
                            <p className="mt-1 min-h-[40px] text-sm text-gray-500">{plan.description}</p>
                            <p className="mt-4 text-3xl font-bold text-gray-900">
                                {brl(price(plan))}
                                <span className="text-sm font-normal text-gray-400">/{cycle === 'monthly' ? 'mês' : 'ano'}</span>
                            </p>
                            <button
                                onClick={() => choose(plan)}
                                disabled={!price(plan)}
                                className="mt-6 rounded-lg bg-blue-600 py-2.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-40"
                            >
                                {selected?.id === plan.id ? 'Selecionado' : 'Assinar'}
                            </button>
                        </div>
                    ))}
                </div>

                {selected && (
                    <form id="checkout-form" onSubmit={submit} className="mx-auto mt-14 max-w-xl rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
                        <h2 className="text-xl font-bold text-gray-900">Seus dados</h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Plano <strong>{selected.display_name}</strong> · {cycle === 'monthly' ? 'Mensal' : 'Anual'} · {brl(price(selected))}
                        </p>

                        <div className="mt-6 grid gap-4">
                            <Field label="Nome da empresa / condomínio" error={form.errors.company_name}>
                                <input className="input" value={form.data.company_name} onChange={(e) => form.setData('company_name', e.target.value)} />
                            </Field>
                            <Field label="Seu nome (síndico)" error={form.errors.admin_name}>
                                <input className="input" value={form.data.admin_name} onChange={(e) => form.setData('admin_name', e.target.value)} />
                            </Field>
                            <Field label="CPF / CNPJ" error={form.errors.document}>
                                <input className="input" value={form.data.document} onChange={(e) => form.setData('document', e.target.value)} />
                            </Field>
                            <Field label="E-mail" error={form.errors.email}>
                                <input type="email" className="input" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                            </Field>
                            <Field label="Telefone" error={form.errors.phone}>
                                <input className="input" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                            </Field>
                            <Field label="Forma de pagamento" error={form.errors.billing_type}>
                                <select className="input" value={form.data.billing_type} onChange={(e) => form.setData('billing_type', e.target.value)}>
                                    <option value="PIX">PIX</option>
                                    <option value="CREDIT_CARD">Cartão de crédito</option>
                                    <option value="BOLETO">Boleto</option>
                                </select>
                            </Field>
                        </div>

                        <button
                            type="submit"
                            disabled={form.processing}
                            className="mt-6 flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 py-3 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            {form.processing && <Loader2 className="h-4 w-4 animate-spin" />}
                            Ir para o pagamento
                        </button>
                        <p className="mt-3 flex items-center justify-center gap-1.5 text-xs text-gray-400">
                            <Check className="h-3.5 w-3.5" /> Pagamento processado com segurança pelo Asaas.
                        </p>
                    </form>
                )}
            </main>

            <style>{`.input{width:100%;border:1px solid #e5e7eb;border-radius:0.5rem;padding:0.55rem 0.75rem;font-size:0.875rem;outline:none}.input:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12)}`}</style>
        </div>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <label className="block">
            <span className="mb-1 block text-sm font-medium text-gray-700">{label}</span>
            {children}
            {error && <span className="mt-1 block text-xs text-red-600">{error}</span>}
        </label>
    );
}
