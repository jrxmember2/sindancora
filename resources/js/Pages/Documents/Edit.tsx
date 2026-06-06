import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import DocumentFields, { DocumentMeta } from './DocumentFields';

interface Option { value: string; label: string }
interface Document {
    id: string; condominium_id: string; title: string; description: string | null;
    category: string; visibility: string;
    valid_from: string | null; valid_until: string | null; renewal_alert_days: number | null;
    storage_object: { original_filename: string | null } | null;
}
interface Props {
    document: Document;
    condominiums: Option[];
    categories: Record<string, string>;
    visibilities: Record<string, string>;
}

export default function DocumentEdit({ document, condominiums, categories, visibilities }: Props) {
    const form = useForm<DocumentMeta>({
        condominium_id: document.condominium_id,
        title: document.title,
        description: document.description ?? '',
        category: document.category,
        visibility: document.visibility,
        valid_from: document.valid_from?.slice(0, 10) ?? '',
        valid_until: document.valid_until?.slice(0, 10) ?? '',
        renewal_alert_days: document.renewal_alert_days != null ? String(document.renewal_alert_days) : '',
    });

    return (
        <AppLayout>
            <Head title="Editar Documento" />
            <div className="space-y-4">
                <div>
                    <Link href={route('documents.index')} className="text-sm text-gray-500 hover:text-gray-700">← Documentos</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Editar Documento</h1>
                </div>

                <div className="mx-auto max-w-2xl space-y-6">
                    <div className="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        {document.storage_object?.original_filename && (
                            <div className="flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600">
                                <FileText className="h-4 w-4 text-gray-400" />
                                <span className="truncate">{document.storage_object.original_filename}</span>
                                <span className="ml-auto text-xs text-gray-400">o arquivo não é alterado na edição</span>
                            </div>
                        )}
                        <DocumentFields
                            data={form.data}
                            setData={(k, v) => form.setData(k, v)}
                            errors={form.errors}
                            condominiums={condominiums}
                            categories={categories}
                            visibilities={visibilities}
                        />
                    </div>

                    <div className="flex items-center justify-between">
                        <Link href={route('documents.index')} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                            Cancelar
                        </Link>
                        <button onClick={() => form.put(route('documents.update', document.id))} disabled={form.processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50">
                            {form.processing ? 'Salvando…' : 'Salvar'}
                        </button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
