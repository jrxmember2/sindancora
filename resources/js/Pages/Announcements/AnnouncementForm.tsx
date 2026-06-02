import { Link } from '@inertiajs/react';
import RichTextEditor from '@/Components/RichTextEditor';

export interface AnnouncementFormData {
    condominium_id: string;
    title: string;
    body: string;
    category: string;
    urgency: string;
    publish_at: string;
    expires_at: string;
}

interface Option { value: string; label: string }

interface Props {
    data: AnnouncementFormData;
    setData: (key: keyof AnnouncementFormData, value: string) => void;
    errors: Partial<Record<keyof AnnouncementFormData, string>>;
    processing: boolean;
    onSubmit: (action: 'draft' | 'publish') => void;
    condominiums: Option[];
    categories: Record<string, string>;
    urgencies: Record<string, string>;
    canPublish: boolean;
    backHref: string;
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

const inputClass = 'w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';

export default function AnnouncementForm({
    data, setData, errors, processing, onSubmit, condominiums, categories, urgencies, canPublish, backHref,
}: Props) {
    return (
        <div className="mx-auto max-w-2xl space-y-6">
            <div className="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <Field label="Condomínio *" error={errors.condominium_id}>
                    <select value={data.condominium_id} onChange={e => setData('condominium_id', e.target.value)} className={inputClass}>
                        <option value="">Selecione…</option>
                        {condominiums.map(c => <option key={c.value} value={c.value}>{c.label}</option>)}
                    </select>
                </Field>

                <Field label="Título *" error={errors.title}>
                    <input value={data.title} onChange={e => setData('title', e.target.value)} className={inputClass} maxLength={200} />
                </Field>

                <div className="grid grid-cols-2 gap-4">
                    <Field label="Categoria *" error={errors.category}>
                        <select value={data.category} onChange={e => setData('category', e.target.value)} className={inputClass}>
                            {Object.entries(categories).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                    </Field>
                    <Field label="Urgência *" error={errors.urgency}>
                        <select value={data.urgency} onChange={e => setData('urgency', e.target.value)} className={inputClass}>
                            {Object.entries(urgencies).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                    </Field>
                </div>

                <Field label="Mensagem *" error={errors.body}>
                    <RichTextEditor value={data.body} onChange={html => setData('body', html)} />
                </Field>

                <div className="grid grid-cols-2 gap-4 border-t border-gray-100 pt-5">
                    <Field label="Agendar publicação" error={errors.publish_at}>
                        <input type="datetime-local" value={data.publish_at} onChange={e => setData('publish_at', e.target.value)} className={inputClass} />
                        <p className="mt-1 text-xs text-gray-400">Deixe vazio para publicar imediatamente ao clicar em Publicar.</p>
                    </Field>
                    <Field label="Expira em" error={errors.expires_at}>
                        <input type="datetime-local" value={data.expires_at} onChange={e => setData('expires_at', e.target.value)} className={inputClass} />
                        <p className="mt-1 text-xs text-gray-400">Após essa data o comunicado deixa de ser exibido.</p>
                    </Field>
                </div>
            </div>

            <div className="flex items-center justify-between">
                <Link href={backHref} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                    Cancelar
                </Link>
                <div className="flex gap-2">
                    <button onClick={() => onSubmit('draft')} disabled={processing} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 disabled:opacity-50">
                        Salvar rascunho
                    </button>
                    {canPublish && (
                        <button onClick={() => onSubmit('publish')} disabled={processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50">
                            {data.publish_at ? 'Agendar' : 'Publicar'}
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}
