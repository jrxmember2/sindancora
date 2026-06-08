import AppLayout from '@/Layouts/AppLayout';
import CondominiumLogo from '@/Components/CondominiumLogo';
import { isValidCnpj, maskCnpj, maskPhone } from '@/lib/masks';
import { Head, useForm, Link } from '@inertiajs/react';
import { ChevronRight, ChevronLeft, Check } from 'lucide-react';
import { useEffect, useState } from 'react';

const STEPS = ['Dados Básicos', 'Endereço', 'Confirmar'];

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
            {children}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

function Input({ className = '', ...props }: React.InputHTMLAttributes<HTMLInputElement>) {
    return (
        <input
            className={`w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 ${className}`}
            {...props}
        />
    );
}

async function fetchCep(cep: string): Promise<any> {
    const raw = cep.replace(/\D/g, '');
    if (raw.length !== 8) return null;
    try {
        const res = await fetch(`https://viacep.com.br/ws/${raw}/json/`);
        const data = await res.json();
        return data.erro ? null : data;
    } catch {
        return null;
    }
}

export default function CondominiumCreate() {
    const [step, setStep] = useState(0);
    const [loadingCep, setLoadingCep] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        name: '', cnpj: '', email: '', phone: '',
        logo: null as File | null,
        zip_code: '', street: '', number: '', complement: '', neighborhood: '', city: '', state: '',
    });

    const [logoPreview, setLogoPreview] = useState<string | null>(null);

    useEffect(() => {
        if (!data.logo) {
            setLogoPreview(null);
            return;
        }

        const url = URL.createObjectURL(data.logo);
        setLogoPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [data.logo]);

    const handleCepBlur = async () => {
        setLoadingCep(true);
        const address = await fetchCep(data.zip_code);
        if (address) {
            setData(d => ({ ...d, street: address.logradouro, neighborhood: address.bairro, city: address.localidade, state: address.uf }));
        }
        setLoadingCep(false);
    };

    const next = () => setStep(s => Math.min(s + 1, STEPS.length - 1));
    const prev = () => setStep(s => Math.max(s - 1, 0));
    const submit = () => post(route('condominiums.store'), { forceFormData: true });

    return (
        <AppLayout>
            <Head title="Novo Condomínio" />
            <div className="mx-auto max-w-2xl space-y-6">
                <div>
                    <Link href={route('condominiums.index')} className="text-sm text-gray-500 hover:text-gray-700">← Condomínios</Link>
                    <h1 className="mt-2 text-2xl font-bold text-gray-900">Novo Condomínio</h1>
                </div>

                {/* Stepper */}
                <div className="flex items-center gap-0">
                    {STEPS.map((s, i) => (
                        <div key={s} className="flex items-center flex-1 last:flex-none">
                            <div className="flex items-center gap-2">
                                <div className={`flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold transition-colors ${i < step ? 'bg-blue-600 text-white' : i === step ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-500'}`}>
                                    {i < step ? <Check className="h-4 w-4" /> : i + 1}
                                </div>
                                <span className={`text-sm ${i === step ? 'font-semibold text-gray-900' : 'text-gray-500'}`}>{s}</span>
                            </div>
                            {i < STEPS.length - 1 && <div className={`flex-1 mx-3 h-px ${i < step ? 'bg-blue-600' : 'bg-gray-200'}`} />}
                        </div>
                    ))}
                </div>

                <div className="rounded-xl bg-white border border-gray-100 shadow-sm p-6 space-y-5">
                    {/* Step 0 — Dados básicos */}
                    {step === 0 && (
                        <>
                            <Field label="Nome do Condomínio *" error={errors.name}>
                                <Input value={data.name} onChange={e => setData('name', e.target.value)} placeholder="Ex: Condomínio Residencial Parque Verde" />
                            </Field>
                            <div className="grid grid-cols-2 gap-4">
                                <Field label="CNPJ" error={errors.cnpj ?? (data.cnpj.length > 0 && !isValidCnpj(data.cnpj) ? 'CNPJ inválido.' : undefined)}>
                                    <Input value={data.cnpj} onChange={e => setData('cnpj', maskCnpj(e.target.value))} placeholder="00.000.000/0000-00" />
                                </Field>
                                <Field label="Telefone" error={errors.phone}>
                                    <Input value={data.phone} onChange={e => setData('phone', maskPhone(e.target.value))} placeholder="(11) 99999-9999" />
                                </Field>
                            </div>
                            <Field label="E-mail" error={errors.email}>
                                <Input type="email" value={data.email} onChange={e => setData('email', e.target.value)} placeholder="contato@condominio.com.br" />
                            </Field>
                            <Field label="Logo do condomínio" error={errors.logo}>
                                <div className="flex items-center gap-4">
                                    <CondominiumLogo
                                        src={logoPreview}
                                        alt={data.name || 'Logo do condominio'}
                                        className="h-16 w-16 shrink-0 rounded-lg"
                                        fallbackClassName="border border-dashed border-gray-200 bg-gray-50 text-gray-300"
                                        iconClassName="h-6 w-6"
                                    />
                                    <input
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        onChange={e => setData('logo', e.target.files?.[0] ?? null)}
                                        className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200"
                                    />
                                </div>
                                <p className="mt-1 text-xs text-gray-500">PNG, JPG ou WEBP até 2 MB.</p>
                            </Field>
                        </>
                    )}

                    {/* Step 1 — Endereço */}
                    {step === 1 && (
                        <>
                            <Field label="CEP" error={errors.zip_code}>
                                <Input
                                    value={data.zip_code}
                                    onChange={e => setData('zip_code', e.target.value)}
                                    onBlur={handleCepBlur}
                                    placeholder="00000-000"
                                    maxLength={9}
                                />
                                {loadingCep && <p className="mt-1 text-xs text-blue-600">Buscando endereço…</p>}
                            </Field>
                            <Field label="Logradouro" error={errors.street}>
                                <Input value={data.street} onChange={e => setData('street', e.target.value)} placeholder="Rua, Avenida…" />
                            </Field>
                            <div className="grid grid-cols-3 gap-4">
                                <Field label="Número" error={errors.number}>
                                    <Input value={data.number} onChange={e => setData('number', e.target.value)} placeholder="123" />
                                </Field>
                                <Field label="Complemento" error={errors.complement}>
                                    <Input value={data.complement} onChange={e => setData('complement', e.target.value)} placeholder="Apto, Bloco…" />
                                </Field>
                                <Field label="Bairro" error={errors.neighborhood}>
                                    <Input value={data.neighborhood} onChange={e => setData('neighborhood', e.target.value)} />
                                </Field>
                            </div>
                            <div className="grid grid-cols-3 gap-4">
                                <Field label="Cidade" error={errors.city}>
                                    <Input value={data.city} onChange={e => setData('city', e.target.value)} className="col-span-2" />
                                </Field>
                                <Field label="Estado" error={errors.state}>
                                    <Input value={data.state} onChange={e => setData('state', e.target.value.toUpperCase())} maxLength={2} placeholder="SP" />
                                </Field>
                            </div>
                        </>
                    )}

                    {/* Step 2 — Confirmar */}
                    {step === 2 && (
                        <div className="space-y-4">
                            <div className="rounded-lg bg-gray-50 p-4 space-y-3">
                                <div className="flex items-center gap-3">
                                    <CondominiumLogo
                                        src={logoPreview}
                                        alt={data.name || 'Logo do condominio'}
                                        className="h-12 w-12 shrink-0 rounded-lg"
                                    />
                                    <div>
                                        <p className="font-semibold text-gray-900">{data.name}</p>
                                        {data.cnpj && <p className="text-sm text-gray-500">CNPJ: {data.cnpj}</p>}
                                    </div>
                                </div>
                                {data.city && (
                                    <p className="text-sm text-gray-600">
                                        {[data.street, data.number, data.complement].filter(Boolean).join(', ')}<br />
                                        {[data.neighborhood, data.city, data.state].filter(Boolean).join(' – ')}
                                        {data.zip_code && ` — CEP ${data.zip_code}`}
                                    </p>
                                )}
                                {data.email && <p className="text-sm text-gray-600">✉ {data.email}</p>}
                                {data.phone && <p className="text-sm text-gray-600">☎ {data.phone}</p>}
                                {data.logo && <p className="text-sm text-gray-600">Logo: {data.logo.name}</p>}
                            </div>
                            <p className="text-sm text-gray-500">Revise as informações acima e clique em <strong>Cadastrar</strong> para confirmar.</p>
                        </div>
                    )}
                </div>

                {/* Navegação */}
                <div className="flex justify-between">
                    <button onClick={prev} disabled={step === 0} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                        <ChevronLeft className="h-4 w-4" /> Voltar
                    </button>
                    {step < STEPS.length - 1 ? (
                        <button onClick={next} disabled={step === 0 && !data.name} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                            Próximo <ChevronRight className="h-4 w-4" />
                        </button>
                    ) : (
                        <button onClick={submit} disabled={processing} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors">
                            <Check className="h-4 w-4" />
                            {processing ? 'Cadastrando…' : 'Cadastrar'}
                        </button>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
