import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { UploadCloud } from 'lucide-react';
import DocumentFields, { DocumentMeta } from './DocumentFields';

interface Option { value: string; label: string }
interface Props {
    condominiums: Option[];
    categories: Record<string, string>;
    visibilities: Record<string, string>;
}

export default function DocumentCreate({ condominiums, categories, visibilities }: Props) {
    const form = useForm<DocumentMeta & { file: File | null }>({
        condominium_id: condominiums.length === 1 ? condominiums[0].value : '',
        title: '', description: '', category: 'other', visibility: 'restricted', file: null,
    });

    const submit = () => form.post(route('documents.store'), { forceFormData: true });

    return (
        <AppLayout>
            <Head title="Enviar Documento" />
            <div className="space-y-4">
                <div>
                    <Link href={route('documents.index')} className="text-sm text-gray-500 hover:text-gray-700">← Documentos</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Enviar Documento</h1>
                </div>

                <div className="mx-auto max-w-2xl space-y-6">
                    <div className="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <DocumentFields
                            data={form.data}
                            setData={(k, v) => form.setData(k, v)}
                            errors={form.errors}
                            condominiums={condominiums}
                            categories={categories}
                            visibilities={visibilities}
                        />

                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Arquivo *</label>
                            <label className="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-200 px-4 py-8 text-center transition-colors hover:border-blue-400 hover:bg-blue-50/40">
                                <UploadCloud className="h-7 w-7 text-gray-400" />
                                {form.data.file ? (
                                    <span className="text-sm font-medium text-gray-700">{form.data.file.name}</span>
                                ) : (
                                    <>
                                        <span className="text-sm font-medium text-gray-600">Clique para selecionar</span>
                                        <span className="text-xs text-gray-400">PDF, Word, Excel, imagens ou ZIP — até 50 MB</span>
                                    </>
                                )}
                                <input
                                    type="file"
                                    className="hidden"
                                    onChange={e => form.setData('file', e.target.files?.[0] ?? null)}
                                />
                            </label>
                            {form.progress && (
                                <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                                    <div className="h-full bg-blue-500" style={{ width: `${form.progress.percentage}%` }} />
                                </div>
                            )}
                            {form.errors.file && <p className="mt-1 text-xs text-red-600">{form.errors.file}</p>}
                        </div>
                    </div>

                    <div className="flex items-center justify-between">
                        <Link href={route('documents.index')} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                            Cancelar
                        </Link>
                        <button onClick={submit} disabled={form.processing || !form.data.file} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50">
                            {form.processing ? 'Enviando…' : 'Enviar'}
                        </button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
