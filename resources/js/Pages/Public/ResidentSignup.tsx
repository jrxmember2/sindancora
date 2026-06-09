import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { FormEventHandler } from 'react';
import PublicLayout from '@/Layouts/PublicLayout';
import TurnstileWidget from '@/Components/TurnstileWidget';
import HoneypotField from '@/Components/HoneypotField';

interface Option {
    value: string;
    label: string;
}

interface Props {
    token: string;
    condominium: { name: string };
    units: Option[];
    relations: Record<string, string>;
    captchaSiteKey: string | null;
}

export default function ResidentSignup({ token, condominium, units, relations, captchaSiteKey }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        document: '',
        email: '',
        phone: '',
        person_type: 'individual',
        relation: 'owner',
        unit_id: '',
        notes: '',
        company_site: '',
        'cf-turnstile-response': '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('public.intake.resident.store', { token }));
    };

    const field = 'mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500';
    const label = 'block text-sm font-medium text-gray-700';

    return (
        <PublicLayout title="Cadastro de morador" subtitle={condominium.name}>
            <Link href={route('public.intake.landing', { token })} className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <ArrowLeft className="h-4 w-4" /> Voltar
            </Link>

            <form onSubmit={submit} className="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
                <div>
                    <label className={label}>Nome completo *</label>
                    <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className={field} />
                    {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                </div>

                <div>
                    <label className={label}>Vínculo *</label>
                    <select value={data.relation} onChange={(e) => setData('relation', e.target.value)} className={field}>
                        {Object.entries(relations).map(([value, text]) => (
                            <option key={value} value={value}>{text}</option>
                        ))}
                    </select>
                    {errors.relation && <p className="mt-1 text-xs text-red-600">{errors.relation}</p>}
                </div>

                <div>
                    <label className={label}>Unidade *</label>
                    <select value={data.unit_id} onChange={(e) => setData('unit_id', e.target.value)} className={field}>
                        <option value="">Selecione...</option>
                        {units.map((u) => (
                            <option key={u.value} value={u.value}>{u.label}</option>
                        ))}
                    </select>
                    {errors.unit_id && <p className="mt-1 text-xs text-red-600">{errors.unit_id}</p>}
                </div>

                <div>
                    <label className={label}>Telefone (WhatsApp) *</label>
                    <input type="tel" value={data.phone} onChange={(e) => setData('phone', e.target.value)} className={field} placeholder="(00) 00000-0000" />
                    {errors.phone && <p className="mt-1 text-xs text-red-600">{errors.phone}</p>}
                </div>

                <div>
                    <label className={label}>E-mail</label>
                    <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className={field} placeholder="Para receber o acesso ao portal" />
                    {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
                </div>

                <div>
                    <label className={label}>CPF/CNPJ</label>
                    <input type="text" value={data.document} onChange={(e) => setData('document', e.target.value)} className={field} />
                    {errors.document && <p className="mt-1 text-xs text-red-600">{errors.document}</p>}
                </div>

                <div>
                    <label className={label}>Observações</label>
                    <textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} rows={2} className={field} />
                    {errors.notes && <p className="mt-1 text-xs text-red-600">{errors.notes}</p>}
                </div>

                <HoneypotField value={data.company_site} onChange={(v) => setData('company_site', v)} />

                {captchaSiteKey && (
                    <div>
                        <TurnstileWidget siteKey={captchaSiteKey} onVerify={(t) => setData('cf-turnstile-response', t)} />
                        {(errors as Record<string, string>).captcha && <p className="mt-1 text-xs text-red-600">{(errors as Record<string, string>).captcha}</p>}
                    </div>
                )}

                <button
                    type="submit"
                    disabled={processing}
                    className="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-60"
                >
                    Enviar para aprovação
                </button>
                <p className="text-center text-xs text-gray-400">
                    A administração vai revisar antes de liberar seu acesso.
                </p>
            </form>
        </PublicLayout>
    );
}
