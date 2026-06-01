import { isValidCpf, maskCpf, maskPhone } from '@/lib/masks';
import { Link } from '@inertiajs/react';

interface PersonFormData {
    name: string; cpf: string; email: string; phone: string; phone2: string;
    birth_date: string; zip_code: string; street: string; number: string;
    complement: string; neighborhood: string; city: string; state: string; notes: string;
}

interface Props {
    data: PersonFormData;
    setData: (key: keyof PersonFormData, value: string) => void;
    errors: Partial<Record<keyof PersonFormData, string>>;
    processing: boolean;
    onSubmit: () => void;
    submitLabel: string;
    backHref: string;
}

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

export default function PersonForm({ data, setData, errors, processing, onSubmit, submitLabel, backHref }: Props) {
    const handleCepBlur = async () => {
        const address = await fetchCep(data.zip_code);
        if (address) {
            setData('street', address.logradouro);
            setData('neighborhood', address.bairro);
            setData('city', address.localidade);
            setData('state', address.uf);
        }
    };

    return (
        <div className="mx-auto max-w-2xl space-y-6">
            <div className="rounded-xl bg-white border border-gray-100 shadow-sm p-6 space-y-5">
                <h2 className="text-sm font-semibold text-gray-700 uppercase tracking-wide">Dados Pessoais</h2>
                <Field label="Nome Completo *" error={errors.name}>
                    <Input value={data.name} onChange={e => setData('name', e.target.value)} />
                </Field>
                <div className="grid grid-cols-2 gap-4">
                    <Field label="CPF" error={errors.cpf ?? (data.cpf.length > 0 && !isValidCpf(data.cpf) ? 'CPF inválido.' : undefined)}>
                        <Input value={maskCpf(data.cpf)} onChange={e => setData('cpf', maskCpf(e.target.value))} placeholder="000.000.000-00" />
                    </Field>
                    <Field label="Data de Nascimento" error={errors.birth_date}>
                        <Input type="date" value={data.birth_date} onChange={e => setData('birth_date', e.target.value)} />
                    </Field>
                </div>
                <div className="grid grid-cols-2 gap-4">
                    <Field label="E-mail" error={errors.email}>
                        <Input type="email" value={data.email} onChange={e => setData('email', e.target.value)} />
                    </Field>
                    <Field label="Telefone" error={errors.phone}>
                        <Input value={maskPhone(data.phone)} onChange={e => setData('phone', maskPhone(e.target.value))} placeholder="(11) 99999-9999" />
                    </Field>
                </div>
                <Field label="Telefone 2" error={errors.phone2}>
                    <Input value={maskPhone(data.phone2)} onChange={e => setData('phone2', maskPhone(e.target.value))} placeholder="(11) 99999-9999" />
                </Field>

                <div className="border-t border-gray-100 pt-5">
                    <h2 className="mb-4 text-sm font-semibold text-gray-700 uppercase tracking-wide">Endereço</h2>
                    <div className="space-y-4">
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
                </div>

                <div className="border-t border-gray-100 pt-5">
                    <Field label="Observações" error={errors.notes}>
                        <textarea
                            value={data.notes}
                            onChange={e => setData('notes', e.target.value)}
                            rows={3}
                            className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 resize-none"
                        />
                    </Field>
                </div>
            </div>

            <div className="flex justify-between">
                <Link href={backHref} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancelar
                </Link>
                <button onClick={onSubmit} disabled={processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors">
                    {processing ? 'Salvando…' : submitLabel}
                </button>
            </div>
        </div>
    );
}
