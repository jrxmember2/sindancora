import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Download, FileText, Plug, RefreshCw, Sparkles, Trash2, UploadCloud, XCircle } from 'lucide-react';
import { useMemo } from 'react';
import type { PageProps } from '@/types';

type Provider = 'anthropic' | 'openai' | 'gemini';

interface AiSetting {
    provider: Provider;
    model: string | null;
    base_url: string | null;
    enabled: boolean;
    has_key: boolean;
    last_checked_at: string | null;
}

interface ProviderDefaults {
    model: string | null;
    base_url: string | null;
}

interface ModelOption {
    value: string;
    label: string;
    description: string;
    recommended: boolean;
}

interface LegalDocument {
    id: string;
    title: string;
    description: string | null;
    category: string;
    category_label: string;
    jurisdiction_level: string;
    jurisdiction_label: string;
    state: string | null;
    city: string | null;
    is_active: boolean;
    original_filename: string | null;
    file_size_bytes: number | null;
    chunks_count: number;
    uploaded_by: string | null;
    created_at: string | null;
}

interface Props {
    setting: AiSetting;
    configured: boolean;
    providerOptions: Record<Provider, string>;
    modelOptions: Record<Provider, ModelOption[]>;
    defaults: Record<Provider, ProviderDefaults>;
    legalDocuments: LegalDocument[];
    legalCategories: Record<string, string>;
    legalJurisdictions: Record<string, string>;
}

function formatBytes(bytes: number | null): string {
    if (!bytes) return '-';
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export default function AiSettings({ setting, configured, providerOptions, modelOptions, defaults, legalDocuments, legalCategories, legalJurisdictions }: Props) {
    const { flash } = usePage<PageProps>().props;

    const availableModelForProvider = (provider: Provider, preferred?: string | null) => {
        const options = modelOptions[provider] ?? [];
        const defaultModel = defaults[provider]?.model;

        if (preferred && options.some((option) => option.value === preferred)) {
            return preferred;
        }

        if (defaultModel && options.some((option) => option.value === defaultModel)) {
            return defaultModel;
        }

        return options.find((option) => option.recommended)?.value ?? options[0]?.value ?? '';
    };

    const form = useForm({
        provider: setting.provider,
        model: availableModelForProvider(setting.provider, setting.model),
        base_url: setting.base_url ?? '',
        api_key: '',
        enabled: setting.enabled,
    });

    const legalForm = useForm<{ title: string; description: string; category: string; jurisdiction_level: string; state: string; city: string; file: File | null; is_active: boolean }>({
        title: '',
        description: '',
        category: 'civil_code',
        jurisdiction_level: 'federal',
        state: '',
        city: '',
        file: null,
        is_active: true,
    });

    const field = 'w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500';
    const selectedProviderModels = modelOptions[form.data.provider] ?? [];
    const selectedModel = useMemo(
        () => selectedProviderModels.find((option) => option.value === form.data.model),
        [form.data.model, selectedProviderModels],
    );
    const modelSelectValue = selectedModel?.value ?? '';

    const changeProvider = (provider: Provider) => {
        const preferredModel = availableModelForProvider(provider, defaults[provider]?.model);

        form.setData({
            ...form.data,
            provider,
            model: preferredModel,
            base_url: defaults[provider]?.base_url ?? '',
        });
    };

    const changeModel = (value: string) => {
        form.setData('model', value);
    };

    const save = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(route('admin.ai.update'), {
            preserveScroll: true,
            onSuccess: () => form.setData('api_key', ''),
        });
    };

    const test = () => router.post(route('admin.ai.test'), {}, { preserveScroll: true });
    const uploadLegalDocument = (e: React.FormEvent) => {
        e.preventDefault();
        legalForm.post(route('admin.ai.legal-documents.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => legalForm.reset('title', 'description', 'file'),
        });
    };
    const toggleLegalDocument = (document: LegalDocument) =>
        router.patch(route('admin.ai.legal-documents.toggle', document.id), {}, { preserveScroll: true });
    const reindexLegalDocument = (document: LegalDocument) =>
        router.post(route('admin.ai.legal-documents.reindex', document.id), {}, { preserveScroll: true });
    const destroyLegalDocument = (document: LegalDocument) => {
        if (confirm(`Remover o documento legal "${document.title}"?`)) {
            router.delete(route('admin.ai.legal-documents.destroy', document.id), { preserveScroll: true });
        }
    };

    return (
        <AdminLayout>
            <Head title="IA da Plataforma" />

            <div className="mx-auto max-w-5xl space-y-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900">
                        <Sparkles className="h-6 w-6 text-blue-600" /> IA da Plataforma
                    </h1>
                    <span className={`inline-flex w-fit items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium ${configured ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                        {configured ? <CheckCircle2 className="h-4 w-4" /> : <XCircle className="h-4 w-4" />}
                        {configured ? 'Configurada' : 'Não configurada'}
                    </span>
                </div>

                <p className="text-sm text-gray-500">
                    Configuração global usada por todos os tenants com o módulo Assistente IA contratado. A chave
                    fica criptografada e nunca é exibida para síndicos, administradores do tenant ou moradores.
                </p>

                {(flash?.success || flash?.error) && (
                    <div className={`rounded-lg border px-4 py-3 text-sm ${flash.success ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800'}`}>
                        {flash.success || flash.error}
                    </div>
                )}

                <form onSubmit={save} className="space-y-5 rounded-xl border border-gray-200 bg-white p-6">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Provedor</label>
                            <select value={form.data.provider} onChange={(e) => changeProvider(e.target.value as Provider)} className={field}>
                                {Object.entries(providerOptions).map(([value, label]) => (
                                    <option key={value} value={value}>{label}</option>
                                ))}
                            </select>
                            {form.errors.provider && <p className="mt-1 text-xs text-red-600">{form.errors.provider}</p>}
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Modelo</label>
                            <select
                                key={form.data.provider}
                                value={modelSelectValue}
                                onChange={(e) => changeModel(e.target.value)}
                                className={field}
                                disabled={selectedProviderModels.length === 0}
                            >
                                <option value="" disabled>
                                    {selectedProviderModels.length === 0 ? 'Nenhum modelo disponivel' : 'Selecione um modelo'}
                                </option>
                                {selectedProviderModels.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label} ({option.value}){option.recommended ? ' - recomendado' : ''}
                                    </option>
                                ))}
                            </select>
                            <p className="mt-1 text-xs text-gray-400">
                                {selectedModel?.description ?? 'Modelos filtrados pelo provedor selecionado e compativeis com o Assistente.'}
                            </p>
                            {form.errors.model && <p className="mt-1 text-xs text-red-600">{form.errors.model}</p>}
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">URL base</label>
                        <input type="url" value={form.data.base_url} onChange={(e) => form.setData('base_url', e.target.value)} placeholder="URL base da API" className={field} />
                        <p className="mt-1 text-xs text-gray-400">Opcional para usar o endpoint padrão do provedor ou um proxy compatível.</p>
                        {form.errors.base_url && <p className="mt-1 text-xs text-red-600">{form.errors.base_url}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Chave global da API</label>
                        <input
                            type="password"
                            value={form.data.api_key}
                            onChange={(e) => form.setData('api_key', e.target.value)}
                            placeholder={setting.has_key ? '•••••••• (configurada - deixe em branco para manter)' : 'Cole a chave do provedor'}
                            className={field}
                            autoComplete="new-password"
                        />
                        {form.errors.api_key && <p className="mt-1 text-xs text-red-600">{form.errors.api_key}</p>}
                    </div>

                    <label className="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" checked={form.data.enabled} onChange={(e) => form.setData('enabled', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                        Integração de IA habilitada
                    </label>

                    <div className="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 text-xs text-gray-500">
                        As regras de foco condominial, uso de documentos e bloqueio de parecer jurídico serão aplicadas
                        no serviço do Assistente, não expostas para edição do tenant.
                    </div>

                    <div className="flex flex-col gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:items-center">
                        <button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                            Salvar
                        </button>
                        <button type="button" onClick={test} className="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <Plug className="h-4 w-4" /> Testar conexão
                        </button>
                        {setting.last_checked_at && (
                            <span className="text-xs text-gray-400 sm:ml-auto">
                                Último teste: {new Date(setting.last_checked_at).toLocaleString('pt-BR')}
                            </span>
                        )}
                    </div>
                </form>

                <section className="space-y-4 rounded-xl border border-gray-200 bg-white p-6">
                    <div className="flex items-center justify-between">
                        <h2 className="flex items-center gap-2 text-lg font-semibold text-gray-900">
                            <FileText className="h-5 w-5 text-blue-600" /> Base legal global
                        </h2>
                        <span className="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">
                            {legalDocuments.length} documento(s)
                        </span>
                    </div>

                    <form onSubmit={uploadLegalDocument} className="grid gap-4 border-b border-gray-100 pb-5 lg:grid-cols-6">
                        <div className="lg:col-span-2">
                            <label className="mb-1 block text-sm font-medium text-gray-700">Titulo</label>
                            <input value={legalForm.data.title} onChange={(e) => legalForm.setData('title', e.target.value)} className={field} maxLength={200} />
                            {legalForm.errors.title && <p className="mt-1 text-xs text-red-600">{legalForm.errors.title}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Categoria</label>
                            <select value={legalForm.data.category} onChange={(e) => legalForm.setData('category', e.target.value)} className={field}>
                                {Object.entries(legalCategories).map(([value, label]) => (
                                    <option key={value} value={value}>{label}</option>
                                ))}
                            </select>
                            {legalForm.errors.category && <p className="mt-1 text-xs text-red-600">{legalForm.errors.category}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Abrangencia</label>
                            <select
                                value={legalForm.data.jurisdiction_level}
                                onChange={(e) => {
                                    const level = e.target.value;
                                    legalForm.setData({
                                        ...legalForm.data,
                                        jurisdiction_level: level,
                                        state: ['state', 'municipal'].includes(level) ? legalForm.data.state : '',
                                        city: level === 'municipal' ? legalForm.data.city : '',
                                    });
                                }}
                                className={field}
                            >
                                {Object.entries(legalJurisdictions).map(([value, label]) => (
                                    <option key={value} value={value}>{label}</option>
                                ))}
                            </select>
                            {legalForm.errors.jurisdiction_level && <p className="mt-1 text-xs text-red-600">{legalForm.errors.jurisdiction_level}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">UF</label>
                            <input
                                value={legalForm.data.state}
                                onChange={(e) => legalForm.setData('state', e.target.value.toUpperCase())}
                                disabled={!['state', 'municipal'].includes(legalForm.data.jurisdiction_level)}
                                className={field}
                                maxLength={2}
                                placeholder="SP"
                            />
                            {legalForm.errors.state && <p className="mt-1 text-xs text-red-600">{legalForm.errors.state}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Municipio</label>
                            <input
                                value={legalForm.data.city}
                                onChange={(e) => legalForm.setData('city', e.target.value)}
                                disabled={legalForm.data.jurisdiction_level !== 'municipal'}
                                className={field}
                                maxLength={120}
                            />
                            {legalForm.errors.city && <p className="mt-1 text-xs text-red-600">{legalForm.errors.city}</p>}
                        </div>
                        <div className="lg:col-span-2">
                            <label className="mb-1 block text-sm font-medium text-gray-700">Arquivo</label>
                            <label className="flex h-[38px] cursor-pointer items-center justify-center gap-2 rounded-lg border border-gray-300 px-3 text-sm text-gray-700 hover:bg-gray-50">
                                <UploadCloud className="h-4 w-4 text-gray-400" />
                                <span className="truncate">{legalForm.data.file?.name ?? 'Selecionar'}</span>
                                <input type="file" className="hidden" onChange={(e) => legalForm.setData('file', e.target.files?.[0] ?? null)} />
                            </label>
                            {legalForm.errors.file && <p className="mt-1 text-xs text-red-600">{legalForm.errors.file}</p>}
                        </div>
                        <div className="flex items-end">
                            <button type="submit" disabled={legalForm.processing || !legalForm.data.file} className="h-[38px] rounded-lg bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                                Enviar
                            </button>
                        </div>
                        <div className="lg:col-span-6">
                            <label className="mb-1 block text-sm font-medium text-gray-700">Descricao</label>
                            <textarea value={legalForm.data.description} onChange={(e) => legalForm.setData('description', e.target.value)} rows={2} className={`${field} resize-none`} maxLength={2000} />
                            {legalForm.errors.description && <p className="mt-1 text-xs text-red-600">{legalForm.errors.description}</p>}
                            <label className="mt-2 flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" checked={legalForm.data.is_active} onChange={(e) => legalForm.setData('is_active', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                Ativo para consulta da IA
                            </label>
                        </div>
                    </form>

                    <div className="overflow-hidden rounded-lg border border-gray-100">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th className="px-4 py-3">Documento</th>
                                    <th className="px-4 py-3">Categoria</th>
                                    <th className="px-4 py-3">Abrangencia</th>
                                    <th className="px-4 py-3">Indexacao</th>
                                    <th className="px-4 py-3">Arquivo</th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {legalDocuments.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-8 text-center text-sm text-gray-500">Nenhum documento legal cadastrado.</td>
                                    </tr>
                                )}
                                {legalDocuments.map((document) => (
                                    <tr key={document.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            <p className="font-medium text-gray-900">{document.title}</p>
                                            {document.description && <p className="line-clamp-1 text-xs text-gray-400">{document.description}</p>}
                                        </td>
                                        <td className="px-4 py-3 text-gray-600">{document.category_label}</td>
                                        <td className="px-4 py-3 text-gray-600">{document.jurisdiction_label}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap items-center gap-1">
                                                <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${document.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                                                    {document.is_active ? 'Ativo' : 'Inativo'}
                                                </span>
                                                <span className="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                                                    {document.chunks_count} trecho(s)
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-xs text-gray-500">
                                            <p className="max-w-[180px] truncate">{document.original_filename ?? '-'}</p>
                                            <p>{formatBytes(document.file_size_bytes)}</p>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-1">
                                                <button type="button" onClick={() => toggleLegalDocument(document)} title={document.is_active ? 'Desativar' : 'Ativar'} className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                                                    <CheckCircle2 className="h-4 w-4" />
                                                </button>
                                                <button type="button" onClick={() => reindexLegalDocument(document)} title="Reindexar" className="rounded p-1.5 text-gray-400 hover:bg-blue-50 hover:text-blue-600">
                                                    <RefreshCw className="h-4 w-4" />
                                                </button>
                                                <a href={route('admin.ai.legal-documents.download', document.id)} title="Baixar" className="rounded p-1.5 text-gray-400 hover:bg-blue-50 hover:text-blue-600">
                                                    <Download className="h-4 w-4" />
                                                </a>
                                                <button type="button" onClick={() => destroyLegalDocument(document)} title="Remover" className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </AdminLayout>
    );
}
