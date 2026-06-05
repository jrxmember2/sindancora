import { useState, useEffect, useRef } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { MessageCircle, Plus, Trash2, QrCode, Building2, RefreshCw, X } from 'lucide-react';

interface Option { value: string; label: string }
interface Connection {
    id: string;
    name: string;
    phone_number: string | null;
    status: string;
    status_label: string;
    bot_enabled: boolean;
    condominium_ids: string[];
    condominiums: string[];
}
interface Props {
    connections: Connection[];
    condominiums: Option[];
    usage: { used: number; limit: number };
    evolutionConfigured: boolean;
    statuses: Record<string, string>;
}

const statusStyle: Record<string, string> = {
    connected: 'bg-green-100 text-green-700',
    connecting: 'bg-amber-100 text-amber-700',
    disconnected: 'bg-gray-100 text-gray-500',
};

export default function WhatsappConnections({ connections, condominiums, usage, evolutionConfigured }: Props) {
    const atLimit = usage.limit !== -1 && usage.used >= usage.limit;

    const createForm = useForm({ name: '' });
    const [qrFor, setQrFor] = useState<Connection | null>(null);
    const [editCondos, setEditCondos] = useState<string | null>(null);

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post(route('whatsapp.connections.store'), {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
    };

    const remove = (c: Connection) => {
        if (confirm(`Remover a conexão "${c.name}"? O número será desconectado.`)) {
            router.delete(route('whatsapp.connections.destroy', c.id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout>
            <Head title="Conexão do WhatsApp" />

            <div className="mx-auto max-w-4xl space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900">
                        <MessageCircle className="h-6 w-6 text-green-600" /> Conexão do WhatsApp
                    </h1>
                    <span className="rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-600">
                        {usage.used}{usage.limit === -1 ? '' : ` / ${usage.limit}`} conexões
                    </span>
                </div>

                {!evolutionConfigured && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        O servidor Evolution ainda não está configurado neste ambiente. Fale com o suporte para habilitar.
                    </div>
                )}

                {/* Criar conexão */}
                <form onSubmit={submitCreate} className="flex items-end gap-3 rounded-xl border border-gray-200 bg-white p-4">
                    <div className="flex-1">
                        <label className="mb-1 block text-sm font-medium text-gray-700">Nova conexão</label>
                        <input
                            type="text"
                            value={createForm.data.name}
                            onChange={(e) => createForm.setData('name', e.target.value)}
                            placeholder="Ex.: Portaria Torre A"
                            className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                        />
                        {createForm.errors.name && <p className="mt-1 text-xs text-red-600">{createForm.errors.name}</p>}
                    </div>
                    <button
                        type="submit"
                        disabled={createForm.processing || atLimit || !evolutionConfigured}
                        className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                        title={atLimit ? 'Limite de conexões do plano atingido' : undefined}
                    >
                        <Plus className="h-4 w-4" /> Criar
                    </button>
                </form>
                {atLimit && (
                    <p className="-mt-4 text-xs text-amber-600">Você atingiu o limite de conexões do seu plano. Contrate conexões adicionais para criar mais.</p>
                )}

                {/* Lista de conexões */}
                {connections.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-gray-300 bg-white py-12 text-center text-sm text-gray-400">
                        Nenhuma conexão ainda. Crie uma e leia o QR Code para parear o WhatsApp.
                    </div>
                ) : (
                    <ul className="space-y-3">
                        {connections.map((c) => (
                            <li key={c.id} className="rounded-xl border border-gray-200 bg-white p-4">
                                <div className="flex items-center gap-3">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <span className="font-semibold text-gray-900">{c.name}</span>
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusStyle[c.status] ?? 'bg-gray-100 text-gray-500'}`}>{c.status_label}</span>
                                        </div>
                                        <p className="mt-0.5 truncate text-sm text-gray-500">
                                            {c.phone_number ? `${c.phone_number} · ` : ''}
                                            {c.condominiums.length > 0 ? c.condominiums.join(', ') : 'Nenhum condomínio alocado'}
                                        </p>
                                    </div>
                                    <button onClick={() => setQrFor(c)} className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        <QrCode className="h-4 w-4" /> {c.status === 'connected' ? 'Reconectar' : 'Conectar'}
                                    </button>
                                    <button onClick={() => setEditCondos(editCondos === c.id ? null : c.id)} className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        <Building2 className="h-4 w-4" /> Condomínios
                                    </button>
                                    <button onClick={() => remove(c)} className="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </div>

                                {editCondos === c.id && (
                                    <CondominiumAllocator connection={c} condominiums={condominiums} onDone={() => setEditCondos(null)} />
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {qrFor && <QrModal connection={qrFor} onClose={() => setQrFor(null)} />}
        </AppLayout>
    );
}

function CondominiumAllocator({ connection, condominiums, onDone }: { connection: Connection; condominiums: Option[]; onDone: () => void }) {
    const form = useForm<{ condominium_ids: string[] }>({ condominium_ids: connection.condominium_ids });

    const toggle = (id: string) => {
        const set = new Set(form.data.condominium_ids);
        set.has(id) ? set.delete(id) : set.add(id);
        form.setData('condominium_ids', Array.from(set));
    };

    const save = () => form.put(route('whatsapp.connections.condominiums', connection.id), { preserveScroll: true, onSuccess: onDone });

    const multi = form.data.condominium_ids.length > 1;

    return (
        <div className="mt-3 rounded-lg border border-gray-100 bg-gray-50 p-3">
            <p className="mb-2 text-xs text-gray-500">Quais condomínios esta conexão atende?</p>
            <div className="grid gap-1.5 sm:grid-cols-2">
                {condominiums.map((c) => (
                    <label key={c.value} className="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" checked={form.data.condominium_ids.includes(c.value)} onChange={() => toggle(c.value)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                        {c.label}
                    </label>
                ))}
            </div>
            {multi && (
                <p className="mt-2 text-xs text-amber-600">Atende mais de um condomínio: será obrigatório um chatbot com menu para direcionar (configurável em fase futura).</p>
            )}
            <div className="mt-3 flex gap-2">
                <button onClick={save} disabled={form.processing} className="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Salvar</button>
                <button onClick={onDone} className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-white">Cancelar</button>
            </div>
        </div>
    );
}

function QrModal({ connection, onClose }: { connection: Connection; onClose: () => void }) {
    const [qr, setQr] = useState<string | null>(null);
    const [code, setCode] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [status, setStatus] = useState(connection.status);
    const [loading, setLoading] = useState(true);
    const timer = useRef<ReturnType<typeof setInterval> | null>(null);

    const loadQr = () => {
        setLoading(true);
        setError(null);
        fetch(route('whatsapp.connections.qr', connection.id), { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((d) => {
                if (d.error) { setError(d.error); return; }
                if (d.base64) setQr(d.base64.startsWith('data:') ? d.base64 : `data:image/png;base64,${d.base64}`);
                setCode(d.code ?? null);
            })
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        loadQr();
        timer.current = setInterval(() => {
            fetch(route('whatsapp.connections.state', connection.id), { headers: { Accept: 'application/json' } })
                .then((r) => r.json())
                .then((d) => {
                    setStatus(d.status);
                    if (d.status === 'connected') {
                        if (timer.current) clearInterval(timer.current);
                        setTimeout(() => { onClose(); router.reload({ only: ['connections'] }); }, 800);
                    }
                })
                .catch(() => {});
        }, 3000);
        return () => { if (timer.current) clearInterval(timer.current); };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
            <div className="w-full max-w-sm rounded-xl bg-white p-6 text-center" onClick={(e) => e.stopPropagation()}>
                <div className="mb-3 flex items-center justify-between">
                    <h2 className="font-semibold text-gray-900">Conectar — {connection.name}</h2>
                    <button onClick={onClose} className="rounded p-1 text-gray-400 hover:bg-gray-100"><X className="h-4 w-4" /></button>
                </div>

                {status === 'connected' ? (
                    <p className="py-8 font-medium text-green-600">✓ Número conectado!</p>
                ) : (
                    <>
                        <p className="mb-3 text-sm text-gray-500">Abra o WhatsApp → Aparelhos conectados → Conectar aparelho e leia o QR.</p>
                        <div className="flex min-h-[220px] items-center justify-center">
                            {loading && !qr ? (
                                <RefreshCw className="h-6 w-6 animate-spin text-gray-300" />
                            ) : error ? (
                                <p className="px-2 text-sm text-red-600">{error}</p>
                            ) : qr ? (
                                <img src={qr} alt="QR Code" className="h-56 w-56" />
                            ) : (
                                <p className="text-sm text-gray-400">Não foi possível gerar o QR. Tente novamente.</p>
                            )}
                        </div>
                        {code && <p className="mt-2 font-mono text-sm tracking-wider text-gray-700">{code}</p>}
                        <button onClick={loadQr} className="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <RefreshCw className="h-4 w-4" /> Gerar novo QR
                        </button>
                    </>
                )}
            </div>
        </div>
    );
}
