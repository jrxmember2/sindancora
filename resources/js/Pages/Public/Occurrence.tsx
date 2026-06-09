import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { FormEventHandler } from 'react';
import PublicLayout from '@/Layouts/PublicLayout';

interface Option {
    value: string;
    label: string;
}

interface Props {
    token: string;
    condominium: { name: string };
    units: Option[];
    categories: Record<string, string>;
}

export default function PublicOccurrence({ token, condominium, units, categories }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        phone: '',
        unit_id: '',
        title: '',
        description: '',
        category: Object.keys(categories)[0] ?? 'other',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('public.intake.occurrence.store', { token }));
    };

    const field = 'mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500';
    const label = 'block text-sm font-medium text-gray-700';

    return (
        <PublicLayout title="Abrir ocorrência" subtitle={condominium.name}>
            <Link href={route('public.intake.landing', { token })} className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <ArrowLeft className="h-4 w-4" /> Voltar
            </Link>

            <form onSubmit={submit} className="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
                <div>
                    <label className={label}>Assunto *</label>
                    <input type="text" value={data.title} onChange={(e) => setData('title', e.target.value)} className={field} placeholder="Resumo do problema" />
                    {errors.title && <p className="mt-1 text-xs text-red-600">{errors.title}</p>}
                </div>

                <div>
                    <label className={label}>Categoria *</label>
                    <select value={data.category} onChange={(e) => setData('category', e.target.value)} className={field}>
                        {Object.entries(categories).map(([value, text]) => (
                            <option key={value} value={value}>{text}</option>
                        ))}
                    </select>
                    {errors.category && <p className="mt-1 text-xs text-red-600">{errors.category}</p>}
                </div>

                <div>
                    <label className={label}>Descrição *</label>
                    <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={4} className={field} placeholder="Conte o que está acontecendo" />
                    {errors.description && <p className="mt-1 text-xs text-red-600">{errors.description}</p>}
                </div>

                <div>
                    <label className={label}>Unidade</label>
                    <select value={data.unit_id} onChange={(e) => setData('unit_id', e.target.value)} className={field}>
                        <option value="">Não informar</option>
                        {units.map((u) => (
                            <option key={u.value} value={u.value}>{u.label}</option>
                        ))}
                    </select>
                    {errors.unit_id && <p className="mt-1 text-xs text-red-600">{errors.unit_id}</p>}
                </div>

                <div>
                    <label className={label}>Seu nome *</label>
                    <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className={field} />
                    {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                </div>

                <div>
                    <label className={label}>Telefone *</label>
                    <input type="tel" value={data.phone} onChange={(e) => setData('phone', e.target.value)} className={field} placeholder="(00) 00000-0000" />
                    {errors.phone && <p className="mt-1 text-xs text-red-600">{errors.phone}</p>}
                </div>

                <div>
                    <label className={label}>E-mail</label>
                    <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className={field} />
                    {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-60"
                >
                    Enviar ocorrência
                </button>
                <p className="text-center text-xs text-gray-400">
                    A administração vai analisar e dar andamento.
                </p>
            </form>
        </PublicLayout>
    );
}
