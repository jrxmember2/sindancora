import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm, router, usePage } from '@inertiajs/react';
import { MessageCircle, CheckCircle2, XCircle, Plug } from 'lucide-react';
import type { PageProps } from '@/types';

interface Setting {
    base_url: string | null;
    webhook_url: string | null;
    enabled: boolean;
    has_key: boolean;
    last_checked_at: string | null;
}
interface Props {
    setting: Setting;
    configured: boolean;
}

export default function EvolutionSettings({ setting, configured }: Props) {
    const { flash } = usePage<PageProps>().props;

    const form = useForm({
        base_url: setting.base_url ?? '',
        api_key: '',
        webhook_url: setting.webhook_url ?? '',
        enabled: setting.enabled,
    });

    const field = 'w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500';

    const save = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(route('admin.evolution.update'), { preserveScroll: true, onSuccess: () => form.setData('api_key', '') });
    };

    const test = () => router.post(route('admin.evolution.test'), {}, { preserveScroll: true });

    return (
        <AdminLayout>
            <Head title="Servidor Evolution (WhatsApp)" />

            <div className="mx-auto max-w-2xl space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900">
                        <MessageCircle className="h-6 w-6 text-green-600" /> Servidor Evolution
                    </h1>
                    <span className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium ${configured ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                        {configured ? <CheckCircle2 className="h-4 w-4" /> : <XCircle className="h-4 w-4" />}
                        {configured ? 'Configurado' : 'Não configurado'}
                    </span>
                </div>

                <p className="text-sm text-gray-500">
                    Conexão da plataforma com o servidor Evolution API (auto-hospedado). Estes dados são
                    globais e usados por todos os tenants para criar instâncias e parear números. A chave
                    nunca é exposta aos síndicos.
                </p>

                {(flash?.success || flash?.error) && (
                    <div className={`rounded-lg border px-4 py-3 text-sm ${flash.success ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800'}`}>
                        {flash.success || flash.error}
                    </div>
                )}

                <form onSubmit={save} className="space-y-4 rounded-xl border border-gray-200 bg-white p-6">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">URL base do servidor</label>
                        <input type="url" value={form.data.base_url} onChange={(e) => form.setData('base_url', e.target.value)} placeholder="https://evolution.seudominio.com" className={field} />
                        {form.errors.base_url && <p className="mt-1 text-xs text-red-600">{form.errors.base_url}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Chave global (API key)</label>
                        <input type="password" value={form.data.api_key} onChange={(e) => form.setData('api_key', e.target.value)} placeholder={setting.has_key ? '•••••••• (configurada — deixe em branco para manter)' : 'Cole a AUTHENTICATION_API_KEY'} className={field} autoComplete="new-password" />
                        {form.errors.api_key && <p className="mt-1 text-xs text-red-600">{form.errors.api_key}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">URL do webhook (recebimento — Fase 2)</label>
                        <input type="url" value={form.data.webhook_url} onChange={(e) => form.setData('webhook_url', e.target.value)} placeholder="https://app.sindancora.com/api/webhooks/evolution" className={field} />
                        <p className="mt-1 text-xs text-gray-400">Opcional por enquanto. Será usado quando o recebimento de mensagens for ligado.</p>
                        {form.errors.webhook_url && <p className="mt-1 text-xs text-red-600">{form.errors.webhook_url}</p>}
                    </div>

                    <label className="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" checked={form.data.enabled} onChange={(e) => form.setData('enabled', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                        Integração habilitada
                    </label>

                    <div className="flex items-center gap-2 border-t border-gray-100 pt-4">
                        <button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Salvar</button>
                        <button type="button" onClick={test} className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <Plug className="h-4 w-4" /> Testar conexão
                        </button>
                        {setting.last_checked_at && (
                            <span className="ml-auto text-xs text-gray-400">Último teste: {new Date(setting.last_checked_at).toLocaleString('pt-BR')}</span>
                        )}
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
