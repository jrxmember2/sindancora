import AppLayout from '@/Layouts/AppLayout';
import { maskCnpj, maskCpf, maskPhone } from '@/lib/masks';
import { Head, useForm } from '@inertiajs/react';
import { Building2, Save, Upload } from 'lucide-react';

interface Profile {
    person_type: 'individual' | 'company';
    legal_name: string;
    trade_name: string | null;
    document: string | null;
    email: string | null;
    phone: string | null;
    primary_color: string | null;
    logo_url: string | null;
    address: {
        zip_code: string | null;
        street: string | null;
        number: string | null;
        complement: string | null;
        neighborhood: string | null;
        city: string | null;
        state: string | null;
    };
}

interface Props {
    profile: Profile;
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
            {children}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

function Input({ className = '', ...props }: React.InputHTMLAttributes<HTMLInputElement>) {
    return <input className={`w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 ${className}`} {...props} />;
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

export default function TenantProfile({ profile }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        person_type: profile.person_type ?? 'company',
        legal_name: profile.legal_name ?? '',
        trade_name: profile.trade_name ?? '',
        document: profile.document ?? '',
        email: profile.email ?? '',
        phone: profile.phone ?? '',
        primary_color: profile.primary_color ?? '#1e40af',
        zip_code: profile.address.zip_code ?? '',
        street: profile.address.street ?? '',
        number: profile.address.number ?? '',
        complement: profile.address.complement ?? '',
        neighborhood: profile.address.neighborhood ?? '',
        city: profile.address.city ?? '',
        state: profile.address.state ?? '',
        logo: null as File | null,
        remove_logo: false,
    });

    const submit = () => post(route('settings.tenant.update'), { forceFormData: true });

    const handleCepBlur = async () => {
        const address = await fetchCep(data.zip_code);
        if (!address) return;

        setData((current) => ({
            ...current,
            street: address.logradouro,
            neighborhood: address.bairro,
            city: address.localidade,
            state: address.uf,
        }));
    };

    const documentValue = data.person_type === 'company' ? maskCnpj(data.document) : maskCpf(data.document);
    const logoPreview = data.logo ? URL.createObjectURL(data.logo) : (data.remove_logo ? null : profile.logo_url);

    return (
        <AppLayout>
            <Head title="Dados do tenant" />

            <div className="mx-auto max-w-4xl space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Dados do tenant</h1>
                    <p className="mt-1 text-sm text-gray-500">Cadastro, marca e contato usados no painel e em relatórios.</p>
                </div>

                <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div className="grid gap-6 lg:grid-cols-[220px_1fr]">
                        <div>
                            <div className="flex h-32 w-32 items-center justify-center overflow-hidden rounded-xl border border-gray-100 bg-gray-50">
                                {logoPreview ? (
                                    <img src={logoPreview} alt={data.trade_name || data.legal_name} className="h-full w-full object-contain" />
                                ) : (
                                    <Building2 className="h-10 w-10 text-gray-300" />
                                )}
                            </div>
                            <label className="mt-4 inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <Upload className="h-4 w-4" />
                                Logo
                                <input
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                    className="hidden"
                                    onChange={(e) => {
                                        setData('logo', e.target.files?.[0] ?? null);
                                        setData('remove_logo', false);
                                    }}
                                />
                            </label>
                            {profile.logo_url && (
                                <label className="mt-3 flex items-center gap-2 text-xs text-gray-600">
                                    <input type="checkbox" checked={data.remove_logo} onChange={(e) => setData('remove_logo', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                    Remover logo atual
                                </label>
                            )}
                            {errors.logo && <p className="mt-2 text-xs text-red-600">{errors.logo}</p>}
                        </div>

                        <div className="space-y-5">
                            <div className="grid gap-4 sm:grid-cols-3">
                                <Field label="Tipo" error={errors.person_type}>
                                    <select value={data.person_type} onChange={(e) => setData('person_type', e.target.value as 'individual' | 'company')} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                        <option value="company">Pessoa jurídica</option>
                                        <option value="individual">Pessoa física</option>
                                    </select>
                                </Field>
                                <Field label={data.person_type === 'company' ? 'Razão social *' : 'Nome completo *'} error={errors.legal_name}>
                                    <Input value={data.legal_name} onChange={(e) => setData('legal_name', e.target.value)} className="sm:col-span-2" />
                                </Field>
                                <Field label="Nome fantasia" error={errors.trade_name}>
                                    <Input value={data.trade_name} onChange={(e) => setData('trade_name', e.target.value)} />
                                </Field>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-3">
                                <Field label={data.person_type === 'company' ? 'CNPJ' : 'CPF'} error={errors.document}>
                                    <Input value={documentValue} onChange={(e) => setData('document', e.target.value)} placeholder={data.person_type === 'company' ? '00.000.000/0000-00' : '000.000.000-00'} />
                                </Field>
                                <Field label="Telefone" error={errors.phone}>
                                    <Input value={maskPhone(data.phone)} onChange={(e) => setData('phone', maskPhone(e.target.value))} />
                                </Field>
                                <Field label="E-mail" error={errors.email}>
                                    <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                                </Field>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-[140px_1fr_120px]">
                                <Field label="CEP" error={errors.zip_code}>
                                    <Input value={data.zip_code} onChange={(e) => setData('zip_code', e.target.value)} onBlur={handleCepBlur} maxLength={9} />
                                </Field>
                                <Field label="Logradouro" error={errors.street}>
                                    <Input value={data.street} onChange={(e) => setData('street', e.target.value)} />
                                </Field>
                                <Field label="Número" error={errors.number}>
                                    <Input value={data.number} onChange={(e) => setData('number', e.target.value)} />
                                </Field>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-4">
                                <Field label="Complemento" error={errors.complement}>
                                    <Input value={data.complement} onChange={(e) => setData('complement', e.target.value)} />
                                </Field>
                                <Field label="Bairro" error={errors.neighborhood}>
                                    <Input value={data.neighborhood} onChange={(e) => setData('neighborhood', e.target.value)} />
                                </Field>
                                <Field label="Cidade" error={errors.city}>
                                    <Input value={data.city} onChange={(e) => setData('city', e.target.value)} />
                                </Field>
                                <Field label="UF" error={errors.state}>
                                    <Input value={data.state} onChange={(e) => setData('state', e.target.value.toUpperCase())} maxLength={2} />
                                </Field>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-[160px_1fr]">
                                <Field label="Cor principal" error={errors.primary_color}>
                                    <div className="flex items-center gap-2">
                                        <input type="color" value={data.primary_color} onChange={(e) => setData('primary_color', e.target.value)} className="h-10 w-12 rounded border border-gray-200 bg-white p-1" />
                                        <Input value={data.primary_color} onChange={(e) => setData('primary_color', e.target.value)} />
                                    </div>
                                </Field>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="flex justify-end">
                    <button onClick={submit} disabled={processing} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        <Save className="h-4 w-4" />
                        {processing ? 'Salvando...' : 'Salvar dados'}
                    </button>
                </div>
            </div>
        </AppLayout>
    );
}
