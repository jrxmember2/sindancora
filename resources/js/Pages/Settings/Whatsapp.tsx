import AppLayout from '@/Layouts/AppLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { MessageCircle, Plug } from 'lucide-react';

interface Setting {
    base_url: string | null;
    instance: string | null;
    enabled: boolean;
    has_api_key: boolean;
}
interface Props { setting: Setting }

const field = 'mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';

export default function WhatsappSettings({ setting }: Props) {
    const { data, setData, patch, processing, errors } = useForm({
        base_url: setting.base_url ?? '',
        instance: setting.instance ?? '',
        api_key: '',
        enabled: setting.enabled,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(route('settings.whatsapp.update'), { preserveScroll: true, onSuccess: () => setData('api_key', '') });
    };

    const test = () => router.post(route('settings.whatsapp.test'), {}, { preserveScroll: true });

    return (
        <AppLayout>
            <Head title="WhatsApp" />

            <div className="mx-auto max-w-2xl">
                <header className="mb-6">
                    <h1 className="flex items-center gap-2 text-lg font-bold text-gray-900"><MessageCircle className="h-5 w-5" /> Integração de WhatsApp (Evolution API)</h1>
                    <p className="text-sm text-gray-500">
                        Conecte uma instância da Evolution API para enviar avisos por WhatsApp (comunicados, cobranças
                        vencidas, ocorrências e reservas), além do in-app e e-mail.
                    </p>
                </header>

                <form onSubmit={submit} className="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div className="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3">
                        <div>
                            <p className="text-sm font-medium text-gray-900">Integração ativa</p>
                            <p className="text-xs text-gray-500">Quando desligada, os avisos seguem por in-app e e-mail.</p>
                        </div>
                        <label className="inline-flex cursor-pointer items-center">
                            <input type="checkbox" checked={data.enabled} onChange={(e) => setData('enabled', e.target.checked)} className="peer sr-only" />
                            <div className="peer h-6 w-11 rounded-full bg-gray-300 after:absolute after:ml-0.5 after:mt-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all peer-checked:bg-green-600 peer-checked:after:translate-x-5" />
                        </label>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700">URL da Evolution API</label>
                        <input value={data.base_url} onChange={(e) => setData('base_url', e.target.value)} className={field} placeholder="https://evolution.suaempresa.com" />
                        {errors.base_url && <p className="mt-1 text-xs text-red-600">{errors.base_url}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700">Instância</label>
                        <input value={data.instance} onChange={(e) => setData('instance', e.target.value)} className={field} placeholder="nome-da-instancia" />
                        {errors.instance && <p className="mt-1 text-xs text-red-600">{errors.instance}</p>}
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
                            placeholder={setting.has_api_key ? 'Deixe em branco para manter a atual' : 'Cole a API key da Evolution'}
                            className={field}
                        />
                        {errors.api_key && <p className="mt-1 text-xs text-red-600">{errors.api_key}</p>}
                        <p className="mt-1 text-xs text-gray-400">A chave é armazenada criptografada.</p>
                    </div>

                    <div className="flex items-center justify-between gap-3 pt-2">
                        <button type="button" onClick={test} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <Plug className="h-4 w-4" /> Testar conexão
                        </button>
                        <button type="submit" disabled={processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                            {processing ? 'Salvando…' : 'Salvar'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
