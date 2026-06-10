import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';
import { HardDrive, Cloud, CheckCircle2, AlertTriangle, Link2, Unlink, ShieldAlert } from 'lucide-react';

interface Connection {
    status: 'connected' | 'disconnected' | 'error';
    account_email: string | null;
    connected_at: string | null;
    last_error: string | null;
}

interface Props {
    configured: boolean;
    connection: Connection | null;
    usage: { limit: string | null; usage: string | null } | null;
}

function formatBytes(value: string | null): string | null {
    if (!value) return null;
    const n = Number(value);
    if (!Number.isFinite(n)) return null;
    const gb = n / 1024 / 1024 / 1024;
    return gb >= 1 ? `${gb.toFixed(1)} GB` : `${(n / 1024 / 1024).toFixed(0)} MB`;
}

export default function Storage({ configured, connection, usage }: Props) {
    const { flash } = usePage<PageProps>().props;
    const isConnected = connection?.status === 'connected';
    const params = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
    const driveStatus = params?.get('drive_status');

    const disconnect = () => {
        if (confirm('Desconectar o Google Drive? A mídia já enviada deixará de ser acessível pelo painel.')) {
            router.delete(route('settings.storage.disconnect'), { preserveScroll: true });
        }
    };

    const limit = formatBytes(usage?.limit ?? null);
    const used = formatBytes(usage?.usage ?? null);

    return (
        <AppLayout>
            <Head title="Armazenamento" />

            <div className="mx-auto max-w-3xl space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Armazenamento externo</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Conecte o Google Drive da sua conta para guardar a mídia do WhatsApp sem consumir a cota do seu plano.
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
                    <HardDrive className="h-3.5 w-3.5" /> Vale apenas para a mídia do atendimento (inbox). Demais anexos seguem no armazenamento da plataforma.
                </p>
            </div>
        </AppLayout>
    );
}
