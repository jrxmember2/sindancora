import AppLayout from '@/Layouts/AppLayout';
import { isValidCnpj, maskCnpj, maskPhone } from '@/lib/masks';
import { Head, Link, useForm } from '@inertiajs/react';

interface Condominium {
    id: string; name: string; cnpj: string | null; email: string | null; phone: string | null;
    zip_code: string | null; street: string | null; number: string | null; complement: string | null;
    neighborhood: string | null; city: string | null; state: string | null; status: string; logo_url: string | null;
}
interface Props { condominium: Condominium }

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
            {children}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

function Input(props: React.InputHTMLAttributes<HTMLInputElement>) {
    return <input className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" {...props} />;
}

async function fetchCep(cep: string): Promise<any> {
    const raw = cep.replace(/\D/g, '');
    if (raw.length !== 8) return null;
    try {
        const res = await fetch(`https://viacep.com.br/ws/${raw}/json/`);
        const data = await res.json();
        return data.erro ? null : data;
    } catch { return null; }
}

export default function CondominiumEdit({ condominium }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        _method: 'patch',
        name: condominium.name,
        cnpj: condominium.cnpj ?? '',
        email: condominium.email ?? '',
        phone: condominium.phone ?? '',
        logo: null as File | null,
        remove_logo: false,
        zip_code: condominium.zip_code ?? '',
        street: condominium.street ?? '',
        number: condominium.number ?? '',
        complement: condominium.complement ?? '',
        neighborhood: condominium.neighborhood ?? '',
        city: condominium.city ?? '',
        state: condominium.state ?? '',
        status: condominium.status,
    });

    const handleCepBlur = async () => {
        const address = await fetchCep(data.zip_code);
        if (address) setData(d => ({ ...d, street: address.logradouro, neighborhood: address.bairro, city: address.localidade, state: address.uf }));
    };

    return (
        <AppLayout>
            <Head title={`Editar — ${condominium.name}`} />
            <div className="mx-auto max-w-2xl space-y-6">
                <div>
                    <Link href={route('condominiums.show', condominium.id)} className="text-sm text-gray-500 hover:text-gray-700">← {condominium.name}</Link>
                    <h1 className="mt-2 text-2xl font-bold text-gray-900">Editar Condomínio</h1>
                </div>

                <div className="rounded-xl bg-white border border-gray-100 shadow-sm p-6 space-y-5">
                    <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide">Dados Básicos</h2>
                    <Field label="Nome *" error={errors.name}>
                        <Input value={data.name} onChange={e => setData('name', e.target.value)} />
                    </Field>
                    <div className="grid grid-cols-2 gap-4">
                        <Field label="CNPJ" error={errors.cnpj ?? (data.cnpj.length > 0 && !isValidCnpj(data.cnpj) ? 'CNPJ inválido.' : undefined)}>
                            <Input value={maskCnpj(data.cnpj)} onChange={e => setData('cnpj', maskCnpj(e.target.value))} placeholder="00.000.000/0000-00" />
                        </Field>
                        <Field label="Telefone" error={errors.phone}>
                            <Input value={maskPhone(data.phone)} onChange={e => setData('phone', maskPhone(e.target.value))} />
                        </Field>
                    </div>
                    <Field label="E-mail" error={errors.email}>
                        <Input type="email" value={data.email} onChange={e => setData('email', e.target.value)} />
                    </Field>
                    <Field label="Logo do condomínio" error={errors.logo}>
                        <div className="flex items-center gap-4">
                            {condominium.logo_url && !data.remove_logo ? (
                                <img src={condominium.logo_url} alt={condominium.name} className="h-16 w-16 rounded-lg border border-gray-100 object-contain bg-white" />
                            ) : (
                                <div className="flex h-16 w-16 items-center justify-center rounded-lg border border-dashed border-gray-200 bg-gray-50 text-xs text-gray-400">Logo</div>
                            )}
                            <div className="flex-1 space-y-2">
                                <input
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    onChange={e => {
                                        setData('logo', e.target.files?.[0] ?? null);
                                        setData('remove_logo', false);
                                    }}
                                    className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200"
                                />
                                {condominium.logo_url && (
                                    <label className="flex items-center gap-2 text-xs text-gray-600">
                                        <input type="checkbox" checked={data.remove_logo} onChange={e => setData('remove_logo', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                        Remover logo atual
                                    </label>
                                )}
                            </div>
                        </div>
                        <p className="mt-1 text-xs text-gray-500">PNG, JPG ou WEBP até 2 MB.</p>
                    </Field>
                    <Field label="Status" error={errors.status}>
                        <select value={data.status} onChange={e => setData('status', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                            <option value="active">Ativo</option>
                            <option value="inactive">Inativo</option>
                        </select>
                    </Field>

                    <div className="border-t border-gray-100 pt-5">
                        <h2 className="mb-4 text-sm font-semibold text-gray-700 uppercase tracking-wide">Endereço</h2>
                        <div className="space-y-4">
                            <Field label="CEP" error={errors.zip_code}>
                                <Input value={data.zip_code} onChange={e => setData('zip_code', e.target.value)} onBlur={handleCepBlur} maxLength={9} />
                            </Field>
                            <Field label="Logradouro" error={errors.street}>
                                <Input value={data.street} onChange={e => setData('street', e.target.value)} />
                            </Field>
                            <div className="grid grid-cols-3 gap-4">
                                <Field label="Número" error={errors.number}>
                                    <Input value={data.number} onChange={e => setData('number', e.target.value)} />
                                </Field>
                                <Field label="Complemento" error={errors.complement}>
                                    <Input value={data.complement} onChange={e => setData('complement', e.target.value)} />
                                </Field>
                                <Field label="Bairro" error={errors.neighborhood}>
                                    <Input value={data.neighborhood} onChange={e => setData('neighborhood', e.target.value)} />
                                </Field>
                            </div>
                            <div className="grid grid-cols-3 gap-4">
                                <Field label="Cidade" error={errors.city}>
                                    <Input value={data.city} onChange={e => setData('city', e.target.value)} />
                                </Field>
                                <Field label="Estado" error={errors.state}>
                                    <Input value={data.state} onChange={e => setData('state', e.target.value.toUpperCase())} maxLength={2} />
                                </Field>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="flex justify-between">
                    <Link href={route('condominiums.show', condominium.id)} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancelar
                    </Link>
                    <button onClick={() => post(route('condominiums.update', condominium.id), { forceFormData: true })} disabled={processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors">
                        {processing ? 'Salvando…' : 'Salvar Alterações'}
                    </button>
                </div>
            </div>
        </AppLayout>
    );
}
