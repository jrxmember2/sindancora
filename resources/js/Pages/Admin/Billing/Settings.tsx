import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { CheckCircle2, XCircle, Loader2 } from 'lucide-react';

interface Settings {
    reminder_days_before: number;
    overdue_day_1: number;
    overdue_day_2: number;
    overdue_day_3: number;
    suspend_day: number;
    trust_unlock_enabled: boolean;
    trust_min_months: number;
    trust_tolerance_days: number;
    trust_cooldown_months: number;
    trust_grace_days: number;
    nfse_enabled: boolean;
    nfse_service_description: string | null;
    nfse_municipal_service_code: string | null;
    nfse_iss_tax: string | null;
    nfse_deductions: string | null;
    nfse_observations: string | null;
    nfse_send_email_to_customer: boolean;
}

interface Props {
    settings: Settings;
    gateway: { configured: boolean; environment: string; webhook_url: string };
}

export default function BillingSettings({ settings, gateway }: Props) {
    const form = useForm<Settings>({ ...settings });
    const [test, setTest] = useState<{ ok: boolean; msg: string } | null>(null);
    const [testing, setTesting] = useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put('/admin/financeiro/configuracoes', { preserveScroll: true });
    };

    const testConnection = async () => {
        setTesting(true);
        setTest(null);
        try {
            const res = await fetch('/admin/financeiro/configuracoes/testar', {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content ?? '' },
            });
            const data = await res.json();
            setTest(data.ok ? { ok: true, msg: data.name } : { ok: false, msg: data.error });
        } catch {
            setTest({ ok: false, msg: 'Falha ao testar conexão.' });
        } finally {
            setTesting(false);
        }
    };

    const num = (k: keyof Settings, label: string, hint?: string) => (
        <label className="block">
            <span className="text-sm font-medium text-gray-700">{label}</span>
            <input type="number" value={form.data[k] as number} onChange={(e) => form.setData(k, Number(e.target.value) as never)}
                className="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
            {hint && <span className="mt-0.5 block text-xs text-gray-400">{hint}</span>}
        </label>
    );

    return (
        <AdminLayout>
            <Head title="Financeiro — Configurações" />

            <form onSubmit={submit} className="space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-gray-900">Configurações de cobrança</h1>
                    <button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        Salvar
                    </button>
                </div>

                <div className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 className="text-sm font-semibold text-gray-700">Gateway Asaas (plataforma)</h2>
                    <div className="mt-3 flex flex-wrap items-center gap-4 text-sm">
                        <span className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 ${gateway.configured ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'}`}>
                            {gateway.configured ? <CheckCircle2 className="h-4 w-4" /> : <XCircle className="h-4 w-4" />}
                            {gateway.configured ? 'Configurado' : 'Sem ASAAS_API_KEY'}
                        </span>
                        <span className="text-gray-500">Ambiente: <strong>{gateway.environment}</strong></span>
                        <button type="button" onClick={testConnection} disabled={testing || !gateway.configured}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-gray-700 hover:bg-gray-50 disabled:opacity-40">
                            {testing && <Loader2 className="h-4 w-4 animate-spin" />} Testar conexão
                        </button>
                        {test && <span className={test.ok ? 'text-green-600' : 'text-red-600'}>{test.ok ? `OK — ${test.msg}` : test.msg}</span>}
                    </div>
                    <p className="mt-3 break-all rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500">
                        Webhook (registre no painel do Asaas): <strong>{gateway.webhook_url}</strong>
                    </p>
                </div>

                <div className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 className="text-sm font-semibold text-gray-700">Régua de cobrança</h2>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        {num('reminder_days_before', 'Lembrete (D-)', 'dias antes do vencimento')}
                        {num('overdue_day_1', 'Aviso 1 (D+)')}
                        {num('overdue_day_2', 'Aviso 2 (D+)')}
                        {num('overdue_day_3', 'Último aviso (D+)')}
                        {num('suspend_day', 'Bloqueio (D+)', 'suspende o tenant')}
                    </div>
                </div>

                <div className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-semibold text-gray-700">Desbloqueio por confiança</h2>
                        <label className="inline-flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" checked={form.data.trust_unlock_enabled} onChange={(e) => form.setData('trust_unlock_enabled', e.target.checked)} />
                            Habilitado
                        </label>
                    </div>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {num('trust_min_months', 'Mín. meses de cliente')}
                        {num('trust_tolerance_days', 'Tolerância de atraso (dias)')}
                        {num('trust_cooldown_months', 'Intervalo entre cortesias (meses)')}
                        {num('trust_grace_days', 'Dias de carência extra')}
                    </div>
                </div>

                <div className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-semibold text-gray-700">NFS-e (Asaas)</h2>
                        <label className="inline-flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" checked={form.data.nfse_enabled} onChange={(e) => form.setData('nfse_enabled', e.target.checked)} />
                            Emitir automaticamente
                        </label>
                    </div>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <label className="block sm:col-span-2">
                            <span className="text-sm font-medium text-gray-700">Descrição do serviço</span>
                            <input value={form.data.nfse_service_description ?? ''} onChange={(e) => form.setData('nfse_service_description', e.target.value)} className="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                        </label>
                        <label className="block">
                            <span className="text-sm font-medium text-gray-700">Código de serviço municipal</span>
                            <input value={form.data.nfse_municipal_service_code ?? ''} onChange={(e) => form.setData('nfse_municipal_service_code', e.target.value)} className="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                        </label>
                        <label className="block">
                            <span className="text-sm font-medium text-gray-700">Alíquota ISS (%)</span>
                            <input type="number" step="0.01" value={form.data.nfse_iss_tax ?? ''} onChange={(e) => form.setData('nfse_iss_tax', e.target.value as never)} className="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                        </label>
                        <label className="block">
                            <span className="text-sm font-medium text-gray-700">Deduções (R$)</span>
                            <input type="number" step="0.01" value={form.data.nfse_deductions ?? ''} onChange={(e) => form.setData('nfse_deductions', e.target.value as never)} className="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                        </label>
                        <label className="block sm:col-span-2">
                            <span className="text-sm font-medium text-gray-700">Observações</span>
                            <textarea value={form.data.nfse_observations ?? ''} onChange={(e) => form.setData('nfse_observations', e.target.value)} rows={2} className="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                        </label>
                        <label className="inline-flex items-center gap-2 text-sm text-gray-600 sm:col-span-2">
                            <input type="checkbox" checked={form.data.nfse_send_email_to_customer} onChange={(e) => form.setData('nfse_send_email_to_customer', e.target.checked)} />
                            Enviar PDF/XML ao cliente por e-mail
                        </label>
                    </div>
                    <p className="mt-3 text-xs text-gray-400">
                        A emissão depende do emissor habilitado/configurado na conta Asaas (dados da empresa e prefeitura).
                    </p>
                </div>
            </form>
        </AdminLayout>
    );
}
