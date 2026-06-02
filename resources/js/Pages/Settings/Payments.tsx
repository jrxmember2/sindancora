import AppLayout from '@/Layouts/AppLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { Copy, Check, Plug, RefreshCw } from 'lucide-react';
import { useState } from 'react';

interface Setting {
    environment: string;
    billing_type: string;
    enabled: boolean;
    wallet_id: string | null;
    webhook_token: string | null;
    has_api_key: boolean;
}
interface Props {
    setting: Setting;
    webhook_url: string;
}

const field =
    'mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';

function CopyButton({ value }: { value: string }) {
    const [copied, setCopied] = useState(false);
    const copy = () => {
        navigator.clipboard.writeText(value);
        setCopied(true);
        setTimeout(() => setCopied(false), 1500);
    };
    return (
        <button type="button" onClick={copy} className="rounded-lg border border-gray-200 p-2 text-gray-500 hover:bg-gray-50">
            {copied ? <Check className="h-4 w-4 text-green-600" /> : <Copy className="h-4 w-4" />}
        </button>
    );
}

export default function PaymentsSettings({ setting, webhook_url }: Props) {
    const { data, setData, patch, processing, errors } = useForm({
        environment: setting.environment,
        enabled: setting.enabled,
        wallet_id: setting.wallet_id ?? '',
        api_key: '',
        regenerate_token: false,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(route('settings.payments.update'), { preserveScroll: true, onSuccess: () => setData('api_key', '') });
    };

    const test = () => router.post(route('settings.payments.test'), {}, { preserveScroll: true });
    const regenerate = () =>
        confirm('Gerar um novo token invalida o anterior. Será preciso reconfigurar a URL no Asaas. Continuar?') &&
        router.patch(route('settings.payments.update'), { ...data, regenerate_token: true }, { preserveScroll: true });

    return (
        <AppLayout>
            <Head title="Pagamentos — Asaas" />

            <div className="mx-auto max-w-2xl">
                <header className="mb-6">
                    <h1 className="text-lg font-bold text-gray-900">Integração de pagamento (Asaas)</h1>
                    <p className="text-sm text-gray-500">
                        Conecte a conta Asaas deste condomínio para gerar boleto e PIX nas cobranças e conciliar os
                        pagamentos automaticamente.
                    </p>
                </header>

                <form onSubmit={submit} className="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div className="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3">
                        <div>
                            <p className="text-sm font-medium text-gray-900">Integração ativa</p>
                            <p className="text-xs text-gray-500">Quando desligada, as cobranças seguem 100% manuais.</p>
                        </div>
                        <label className="inline-flex cursor-pointer items-center">
                            <input
                                type="checkbox"
                                checked={data.enabled}
                                onChange={(e) => setData('enabled', e.target.checked)}
                                className="peer sr-only"
                            />
                            <div className="peer h-6 w-11 rounded-full bg-gray-300 after:absolute after:ml-0.5 after:mt-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all peer-checked:bg-green-600 peer-checked:after:translate-x-5" />
                        </label>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Ambiente</label>
                            <select value={data.environment} onChange={(e) => setData('environment', e.target.value)} className={field}>
                                <option value="sandbox">Sandbox (testes)</option>
                                <option value="production">Produção</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Formas de pagamento</label>
                            <input value="Boleto + PIX" disabled className={`${field} bg-gray-50 text-gray-500`} />
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700">
                            Chave de API {setting.has_api_key && <span className="text-xs font-normal text-green-600">(configurada)</span>}
                        </label>
                        <input
                            type="password"
                            autoComplete="off"
                            value={data.api_key}
                            onChange={(e) => setData('api_key', e.target.value)}
                            placeholder={setting.has_api_key ? 'Deixe em branco para manter a atual' : 'Cole a chave de API do Asaas'}
                            className={field}
                        />
                        {errors.api_key && <p className="mt-1 text-xs text-red-600">{errors.api_key}</p>}
                        <p className="mt-1 text-xs text-gray-400">
                            Encontre em Asaas → Integrações → Chave de API. A chave é armazenada criptografada.
                        </p>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700">Wallet ID (opcional)</label>
                        <input value={data.wallet_id} onChange={(e) => setData('wallet_id', e.target.value)} className={field} />
                    </div>

                    <div className="flex items-center justify-between gap-3 pt-2">
                        <button
                            type="button"
                            onClick={test}
                            className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            <Plug className="h-4 w-4" /> Testar conexão
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            {processing ? 'Salvando…' : 'Salvar'}
                        </button>
                    </div>
                </form>

                {/* Webhook */}
                <div className="mt-6 space-y-4 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div>
                        <h2 className="text-sm font-semibold text-gray-900">Webhook de conciliação</h2>
                        <p className="text-xs text-gray-500">
                            No painel Asaas → Integrações → Webhooks, cadastre a URL abaixo e informe o token como
                            "Token de autenticação".
                        </p>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-500">URL do webhook</label>
                        <div className="mt-1 flex gap-2">
                            <input readOnly value={webhook_url} className={`${field} bg-gray-50`} />
                            <CopyButton value={webhook_url} />
                        </div>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-500">Token de autenticação</label>
                        <div className="mt-1 flex gap-2">
                            <input
                                readOnly
                                value={setting.webhook_token ?? 'Salve a configuração para gerar o token'}
                                className={`${field} bg-gray-50 font-mono text-xs`}
                            />
                            {setting.webhook_token && <CopyButton value={setting.webhook_token} />}
                            <button
                                type="button"
                                onClick={regenerate}
                                title="Gerar novo token"
                                className="rounded-lg border border-gray-200 p-2 text-gray-500 hover:bg-gray-50"
                            >
                                <RefreshCw className="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
