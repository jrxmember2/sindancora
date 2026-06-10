import AppLayout from '@/Layouts/AppLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { PageProps } from '@/types';
import FreeSpaceModal from '@/Components/FreeSpaceModal';
import { HardDrive, Cloud, CheckCircle2, AlertTriangle, Link2, Unlink, ShieldAlert, Database, Trash2 } from 'lucide-react';

interface Connection {
    status: 'connected' | 'disconnected' | 'error';
    account_email: string | null;
    connected_at: string | null;
    last_error: string | null;
}

interface PlanUsage {
    used_gb: number;
    quota_gb: number;
    percentage_used: number;
    is_near_limit: boolean;
}

interface Cleanup {
    mode: 'off' | 'date' | 'quota';
    retention_days: number | null;
}

interface Props {
    configured: boolean;
    connection: Connection | null;
    usage: { limit: string | null; usage: string | null } | null;
    planUsage: PlanUsage;
    cleanup: Cleanup;
}

function formatBytes(value: string | null): string | null {
    if (!value) return null;
    const n = Number(value);
    if (!Number.isFinite(n)) return null;
    const gb = n / 1024 / 1024 / 1024;
    return gb >= 1 ? `${gb.toFixed(1)} GB` : `${(n / 1024 / 1024).toFixed(0)} MB`;
}

export default function Storage({ configured, connection, usage, planUsage, cleanup }: Props) {
    const { flash } = usePage<PageProps>().props;
    const [freeOpen, setFreeOpen] = useState(false);
    const isConnected = connection?.status === 'connected';
    const params = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
    const driveStatus = params?.get('drive_status');

    const cleanupForm = useForm<{ mode: Cleanup['mode']; retention_days: number }>({
        mode: cleanup.mode,
        retention_days: cleanup.retention_days ?? 90,
    });

    const saveCleanup = (e: React.FormEvent) => {
        e.preventDefault();
        cleanupForm.put(route('settings.storage.cleanup'), { preserveScroll: true });
    };

    const disconnect = () => {
        if (confirm('Desconectar o Google Drive? A mídia já enviada deixará de ser acessível pelo painel.')) {
            router.delete(route('settings.storage.disconnect'), { preserveScroll: true });
        }
    };

    const pct = Math.round(planUsage.percentage_used);
    const limit = formatBytes(usage?.limit ?? null);
    const used = formatBytes(usage?.usage ?? null);

    return (
        <AppLayout>
            <Head title="Armazenamento" />

            <div className="mx-auto max-w-3xl space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Armazenamento</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Uso do plano, limpeza de mídia do WhatsApp e conexão com o Google Drive.
                    </p>
                </div>

                {driveStatus === 'connected' && (
                    <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        Google Drive conectado com sucesso.
                    </div>
                )}
                {driveStatus === 'error' && (
                    <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        Não foi possível concluir a conexão com o Google Drive. Tente novamente.
                    </div>
                )}
                {flash?.success && (
                    <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{flash.success}</div>
                )}
                {flash?.error && (
                    <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{flash.error}</div>
                )}

                {/* Uso do plano */}
                <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div className="flex items-center justify-between">
                        <h2 className="flex items-center gap-2 text-base font-semibold text-gray-900">
                            <Database className="h-5 w-5 text-blue-600" /> Uso do armazenamento
                        </h2>
                        <button
                            onClick={() => setFreeOpen(true)}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                        >
                            <Trash2 className="h-4 w-4" /> Liberar espaço
                        </button>
                    </div>
                    <div className="mt-4">
                        <div className="h-2.5 w-full overflow-hidden rounded-full bg-gray-100">
                            <div
                                className={`h-full rounded-full ${planUsage.is_near_limit ? 'bg-red-500' : 'bg-blue-500'}`}
                                style={{ width: `${Math.min(pct, 100)}%` }}
                            />
                        </div>
                        <div className="mt-2 flex items-center justify-between text-sm">
                            <span className={planUsage.is_near_limit ? 'font-medium text-red-600' : 'text-gray-600'}>
                                {pct}% utilizado
                            </span>
                            <span className="text-gray-500">{planUsage.used_gb} GB de {planUsage.quota_gb} GB</span>
                        </div>
                    </div>
                </div>

                {/* Limpeza automática de mídia do WhatsApp */}
                <form onSubmit={saveCleanup} className="space-y-4 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 className="flex items-center gap-2 text-base font-semibold text-gray-900">
                        <Trash2 className="h-5 w-5 text-gray-500" /> Limpeza automática de mídia do WhatsApp
                    </h2>
                    <p className="text-sm text-gray-500">
                        Apaga automaticamente a mídia mais antiga do atendimento (só do sistema, não do celular).
                        Não afeta a mídia que estiver no seu Google Drive.
                    </p>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Modo</label>
                            <select
                                value={cleanupForm.data.mode}
                                onChange={(e) => cleanupForm.setData('mode', e.target.value as Cleanup['mode'])}
                                className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                                <option value="off">Desativada (só avisa aos 85%)</option>
                                <option value="date">Por data (apagar mais antigas que X dias)</option>
                                <option value="quota">Por cota (apagar ao atingir 85%)</option>
                            </select>
                        </div>

                        {cleanupForm.data.mode === 'date' && (
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Manter por (dias)</label>
                                <input
                                    type="number"
                                    min={1}
                                    max={3650}
                                    value={cleanupForm.data.retention_days}
                                    onChange={(e) => cleanupForm.setData('retention_days', Number(e.target.value))}
                                    className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                                />
                                {cleanupForm.errors.retention_days && (
                                    <p className="mt-1 text-xs text-red-600">{cleanupForm.errors.retention_days}</p>
                                )}
                            </div>
                        )}
                    </div>

                    <div className="border-t border-gray-100 pt-4">
                        <button
                            type="submit"
                            disabled={cleanupForm.processing}
                            className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            Salvar política
                        </button>
                    </div>
                </form>

                {/* Google Drive */}
                <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div className="flex items-start gap-4">
                        <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                            <Cloud className="h-6 w-6" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-2">
                                <h2 className="text-base font-semibold text-gray-900">Google Drive</h2>
                                {isConnected ? (
                                    <span className="inline-flex items-center gap-1 rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">
                                        <CheckCircle2 className="h-3.5 w-3.5" /> Conectado
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">
                                        Desconectado
                                    </span>
                                )}
                            </div>

                            {isConnected ? (
                                <div className="mt-1 space-y-0.5 text-sm text-gray-600">
                                    {connection?.account_email && <p>Conta: <span className="font-medium text-gray-900">{connection.account_email}</span></p>}
                                    {(used || limit) && (
                                        <p>Uso do Drive: <span className="font-medium text-gray-900">{used ?? '—'}{limit ? ` de ${limit}` : ''}</span></p>
                                    )}
                                </div>
                            ) : (
                                <p className="mt-1 text-sm text-gray-500">
                                    A partir da conexão, as próximas mídias do atendimento são gravadas no seu Drive.
                                </p>
                            )}

                            {connection?.status === 'error' && connection.last_error && (
                                <p className="mt-2 inline-flex items-center gap-1.5 text-xs text-amber-700">
                                    <AlertTriangle className="h-4 w-4" /> {connection.last_error}
                                </p>
                            )}

                            <div className="mt-4">
                                {!configured ? (
                                    <p className="text-sm text-gray-400">
                                        A integração ainda não foi habilitada pela plataforma. Fale com o suporte.
                                    </p>
                                ) : isConnected ? (
                                    <button
                                        onClick={disconnect}
                                        className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                                    >
                                        <Unlink className="h-4 w-4" /> Desconectar
                                    </button>
                                ) : (
                                    <a
                                        href={route('settings.storage.connect')}
                                        className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
                                    >
                                        <Link2 className="h-4 w-4" /> Conectar Google Drive
                                    </a>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Aviso de responsabilidade */}
                <div className="rounded-xl border border-amber-200 bg-amber-50 p-5">
                    <div className="flex gap-3">
                        <ShieldAlert className="h-5 w-5 flex-shrink-0 text-amber-600" />
                        <div className="text-sm text-amber-800">
                            <p className="font-semibold">Os arquivos ficam no seu Google Drive.</p>
                            <ul className="mt-1.5 list-disc space-y-1 pl-4">
                                <li>A mídia armazenada no Drive <strong>não consome a cota</strong> do seu plano.</li>
                                <li>Backup, disponibilidade e exclusão dos arquivos são de <strong>sua responsabilidade</strong>.</li>
                                <li>Se você desconectar ou revogar o acesso, a mídia já enviada deixa de abrir no painel.</li>
                                <li>Usamos apenas a permissão <code className="rounded bg-amber-100 px-1">drive.file</code> — acessamos somente os arquivos criados por este app.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <p className="flex items-center gap-1.5 text-xs text-gray-400">
                    <HardDrive className="h-3.5 w-3.5" /> A limpeza e o Drive valem apenas para a mídia do atendimento (inbox). Demais anexos seguem no armazenamento da plataforma.
                </p>
            </div>

            <FreeSpaceModal open={freeOpen} onClose={() => setFreeOpen(false)} />
        </AppLayout>
    );
}
