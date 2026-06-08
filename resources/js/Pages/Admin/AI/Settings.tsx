import AdminLayout from '@/Layouts/AdminLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Plug, Sparkles, XCircle } from 'lucide-react';
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

interface Props {
    setting: AiSetting;
    configured: boolean;
    runtimeSupported: boolean;
    providerOptions: Record<Provider, string>;
    defaults: Record<Provider, ProviderDefaults>;
}

export default function AiSettings({ setting, configured, runtimeSupported, providerOptions, defaults }: Props) {
    const { flash } = usePage<PageProps>().props;

    const form = useForm({
        provider: setting.provider,
        model: setting.model ?? '',
        base_url: setting.base_url ?? '',
        api_key: '',
        enabled: setting.enabled,
    });

    const field = 'w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500';
    const selectedRuntimeSupported = form.data.provider === 'anthropic';

    const changeProvider = (provider: Provider) => {
        form.setData({
            ...form.data,
            provider,
            model: defaults[provider]?.model ?? '',
            base_url: defaults[provider]?.base_url ?? '',
        });
    };

    const save = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(route('admin.ai.update'), {
            preserveScroll: true,
            onSuccess: () => form.setData('api_key', ''),
        });
    };

    const test = () => router.post(route('admin.ai.test'), {}, { preserveScroll: true });

    return (
        <AdminLayout>
            <Head title="IA da Plataforma" />

            <div className="mx-auto max-w-3xl space-y-6">
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

                {(!runtimeSupported || !selectedRuntimeSupported) && (
                    <div className="flex gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <AlertTriangle className="mt-0.5 h-4 w-4 flex-shrink-0" />
                        <p>
                            OpenAI e Gemini já podem ser salvos aqui para preparar a configuração, mas a execução do
                            Assistente nesta etapa ainda usa Claude / Anthropic.
                        </p>
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
                            <input type="text" value={form.data.model} onChange={(e) => form.setData('model', e.target.value)} placeholder="Modelo do provedor" className={field} />
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
            </div>
        </AdminLayout>
    );
}
