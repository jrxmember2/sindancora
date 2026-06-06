import { maskCpfCnpj, maskPhone } from '@/lib/masks';
import { Link } from '@inertiajs/react';

export interface SupplierFormData {
    name: string;
    category: string;
    document: string;
    contact_name: string;
    phone: string;
    email: string;
    website: string;
    zip_code: string;
    street: string;
    number: string;
    complement: string;
    neighborhood: string;
    city: string;
    state: string;
    notes: string;
    is_active: boolean;
    condominium_ids: string[];
}

interface Option { value: string; label: string }

interface Props {
    data: SupplierFormData;
    setData: (key: keyof SupplierFormData, value: string | boolean | string[]) => void;
    errors: Partial<Record<keyof SupplierFormData, string>>;
    processing: boolean;
    onSubmit: () => void;
    submitLabel: string;
    backHref: string;
    categories: Record<string, string>;
    condominiums: Option[];
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

export default function SupplierForm({ data, setData, errors, processing, onSubmit, submitLabel, backHref, categories, condominiums }: Props) {
    const handleCepBlur = async () => {
        const address = await fetchCep(data.zip_code);
        if (address) {
            setData('street', address.logradouro);
            setData('neighborhood', address.bairro);
            setData('city', address.localidade);
            setData('state', address.uf);
        }
    };

    const toggleCondominium = (id: string) => {
        setData('condominium_ids', data.condominium_ids.includes(id)
            ? data.condominium_ids.filter(c => c !== id)
            : [...data.condominium_ids, id]);
    };

    return (
        <div className="mx-auto max-w-2xl space-y-6">
            <div className="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-700">Dados do Fornecedor</h2>
                <Field label="Nome / Razão Social *" error={errors.name}>
                    <Input value={data.name} onChange={e => setData('name', e.target.value)} />
                </Field>
                <div className="grid grid-cols-2 gap-4">
                    <Field label="Categoria" error={errors.category}>
                        <select value={data.category} onChange={e => setData('category', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                            <option value="">—</option>
                            {Object.entries(categories).map(([slug, label]) => <option key={slug} value={slug}>{label}</option>)}
                        </select>
                    </Field>
                    <Field label="CPF / CNPJ" error={errors.document}>
                        <Input value={maskCpfCnpj(data.document)} onChange={e => setData('document', maskCpfCnpj(e.target.value))} placeholder="00.000.000/0000-00" />
                    </Field>
                </div>
                <div className="grid grid-cols-2 gap-4">
                    <Field label="Contato (pessoa)" error={errors.contact_name}>
                        <Input value={data.contact_name} onChange={e => setData('contact_name', e.target.value)} />
                    </Field>
                    <Field label="Telefone" error={errors.phone}>
                        <Input value={maskPhone(data.phone)} onChange={e => setData('phone', maskPhone(e.target.value))} placeholder="(11) 99999-9999" />
                    </Field>
                </div>
                <div className="grid grid-cols-2 gap-4">
                    <Field label="E-mail" error={errors.email}>
                        <Input type="email" value={data.email} onChange={e => setData('email', e.target.value)} />
                    </Field>
                    <Field label="Site" error={errors.website}>
                        <Input value={data.website} onChange={e => setData('website', e.target.value)} placeholder="https://" />
                    </Field>
                </div>

                <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" checked={data.is_active} onChange={e => setData('is_active', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                    Fornecedor ativo
                </label>
            </div>

            {condominiums.length > 0 && (
                <div className="space-y-3 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-700">Condomínios atendidos</h2>
                    <p className="text-xs text-gray-500">Opcional. Deixe em branco para um fornecedor disponível a todos os condomínios.</p>
                    <div className="grid grid-cols-2 gap-2">
                        {condominiums.map(c => (
                            <label key={c.value} className="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" checked={data.condominium_ids.includes(c.value)} onChange={() => toggleCondominium(c.value)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                {c.label}
                            </label>
                        ))}
                    </div>
                </div>
            )}

            <div className="space-y-4 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-700">Endereço</h2>
                <Field label="CEP" error={errors.zip_code}>
                    <Input value={data.zip_code} onChange={e => setData('zip_code', e.target.value)} onBlur={handleCepBlur} maxLength={9} placeholder="00000-000" />
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
                    <div className="col-span-2">
                        <Field label="Cidade" error={errors.city}>
                            <Input value={data.city} onChange={e => setData('city', e.target.value)} />
                        </Field>
                    </div>
                    <Field label="Estado" error={errors.state}>
                        <Input value={data.state} onChange={e => setData('state', e.target.value.toUpperCase())} maxLength={2} placeholder="SP" />
                    </Field>
                </div>
            </div>

            <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <Field label="Observações" error={errors.notes}>
                    <textarea value={data.notes} onChange={e => setData('notes', e.target.value)} rows={3} className="w-full resize-none rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                </Field>
            </div>

            <div className="flex justify-between">
                <Link href={backHref} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">Cancelar</Link>
                <button onClick={onSubmit} disabled={processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50">
                    {processing ? 'Salvando…' : submitLabel}
                </button>
            </div>
        </div>
    );
}
