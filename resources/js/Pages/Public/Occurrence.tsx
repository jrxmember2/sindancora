import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ImagePlus, X } from 'lucide-react';
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
    categories: Record<string, string>;
    captchaSiteKey: string | null;
}

export default function PublicOccurrence({ token, condominium, units, categories, captchaSiteKey }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        phone: '',
        unit_id: '',
        title: '',
        description: '',
        category: Object.keys(categories)[0] ?? 'other',
        photos: [] as File[],
        company_site: '',
        'cf-turnstile-response': '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('public.intake.occurrence.store', { token }), { forceFormData: true });
    };

    const addPhotos = (files: FileList | null) => {
        if (!files) return;
        setData('photos', [...data.photos, ...Array.from(files)].slice(0, 3));
    };

    const removePhoto = (idx: number) => {
        setData('photos', data.photos.filter((_, i) => i !== idx));
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

                <div>
                    <label className={label}>Fotos (até 3)</label>
                    <div className="mt-1 flex flex-wrap gap-2">
                        {data.photos.map((file, idx) => (
                            <div key={idx} className="relative h-20 w-20 overflow-hidden rounded-lg border border-gray-200">
                                <img src={URL.createObjectURL(file)} alt="" className="h-full w-full object-cover" />
                                <button type="button" onClick={() => removePhoto(idx)} className="absolute right-0.5 top-0.5 rounded-full bg-black/60 p-0.5 text-white">
                                    <X className="h-3 w-3" />
                                </button>
                            </div>
                        ))}
                        {data.photos.length < 3 && (
                            <label className="flex h-20 w-20 cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-gray-300 text-gray-400 hover:border-gray-400">
                                <ImagePlus className="h-5 w-5" />
                                <span className="text-[10px]">Adicionar</span>
                                <input type="file" accept="image/png,image/jpeg,image/webp" multiple className="hidden" onChange={(e) => addPhotos(e.target.files)} />
                            </label>
                        )}
                    </div>
                    {errors.photos && <p className="mt-1 text-xs text-red-600">{errors.photos}</p>}
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
                    Enviar ocorrência
                </button>
                <p className="text-center text-xs text-gray-400">
                    A administração vai analisar e dar andamento.
                </p>
            </form>
        </PublicLayout>
    );
}
