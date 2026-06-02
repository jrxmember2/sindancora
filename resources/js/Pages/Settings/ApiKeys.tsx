import AppLayout from '@/Layouts/AppLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { KeyRound, Plus, Trash2, Copy, Check, X, ShieldAlert } from 'lucide-react';
import { useState } from 'react';

interface ApiKeyRow {
    id: string; name: string; key_prefix: string; scopes: string[];
    expires_at: string | null; last_used_at: string | null; revoked_at: string | null;
    active: boolean; created_by: string | null; created_at: string | null;
}
interface ScopeOption { value: string; label: string }
interface LogRow { method: string; path: string; status_code: number | null; duration_ms: number | null; created_at: string }
interface Props {
    keys: ApiKeyRow[];
    scopes: ScopeOption[];
    logs: LogRow[];
    newKey: string | null;
}

const field = 'mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';
const fmtDate = (iso: string | null) => (iso ? new Date(iso).toLocaleString('pt-BR') : '—');

function CreateModal({ scopes, onClose }: { scopes: ScopeOption[]; onClose: () => void }) {
    const { data, setData, post, processing, errors } = useForm<{ name: string; scopes: string[]; expires_at: string }>({
        name: '', scopes: [], expires_at: '',
    });

    const toggle = (scope: string) =>
        setData('scopes', data.scopes.includes(scope) ? data.scopes.filter((s) => s !== scope) : [...data.scopes, scope]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('api-keys.store'), { onSuccess: onClose });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <form onSubmit={submit} className="w-full max-w-lg space-y-4 rounded-2xl bg-white p-6 shadow-xl">
                <div className="flex items-center justify-between">
                    <h2 className="text-base font-semibold text-gray-900">Nova API Key</h2>
                    <button type="button" onClick={onClose}><X className="h-5 w-5 text-gray-400 hover:text-gray-600" /></button>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Nome *</label>
                    <input value={data.name} onChange={(e) => setData('name', e.target.value)} className={field} placeholder="Ex.: Integração ERP" />
                    {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Escopos *</label>
                    <div className="mt-2 grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                        {scopes.map((s) => (
                            <label key={s.value} className="flex items-start gap-2 rounded-lg border border-gray-100 px-3 py-2 text-sm hover:bg-gray-50">
                                <input type="checkbox" checked={data.scopes.includes(s.value)} onChange={() => toggle(s.value)} className="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                <span>
                                    <span className="block font-mono text-xs text-gray-900">{s.value}</span>
                                    <span className="block text-xs text-gray-500">{s.label}</span>
                                </span>
                            </label>
                        ))}
                    </div>
                    {errors.scopes && <p className="mt-1 text-xs text-red-600">{errors.scopes}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Expira em (opcional)</label>
                    <input type="date" value={data.expires_at} onChange={(e) => setData('expires_at', e.target.value)} className={field} />
                    {errors.expires_at && <p className="mt-1 text-xs text-red-600">{errors.expires_at}</p>}
                </div>
                <div className="flex justify-end gap-2 pt-1">
                    <button type="button" onClick={onClose} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button type="submit" disabled={processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        {processing ? 'Criando…' : 'Criar chave'}
                    </button>
                </div>
            </form>
        </div>
    );
}

export default function ApiKeys({ keys, scopes, logs, newKey }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [copied, setCopied] = useState(false);

    const copy = (v: string) => {
        navigator.clipboard.writeText(v);
        setCopied(true);
        setTimeout(() => setCopied(false), 1500);
    };

    const revoke = (id: string) => {
        if (confirm('Revogar esta API Key? Aplicações que a usam perderão acesso imediatamente.')) {
            router.delete(route('api-keys.destroy', id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout>
            <Head title="API Keys" />
            {createOpen && <CreateModal scopes={scopes} onClose={() => setCreateOpen(false)} />}

            <div className="mx-auto max-w-4xl">
                <header className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-lg font-bold text-gray-900"><KeyRound className="h-5 w-5" /> API Keys</h1>
                        <p className="text-sm text-gray-500">Chaves para a API pública (<code className="text-xs">/api/v1</code>). Use no header <code className="text-xs">Authorization: Bearer sk_...</code>.</p>
                    </div>
                    <button onClick={() => setCreateOpen(true)} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        <Plus className="h-4 w-4" /> Nova chave
                    </button>
                </header>

                {/* Chave recém-criada (exibida uma única vez) */}
                {newKey && (
                    <div className="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <p className="flex items-center gap-2 text-sm font-semibold text-amber-800"><ShieldAlert className="h-4 w-4" /> Copie sua chave agora — ela não será exibida novamente.</p>
                        <div className="mt-2 flex gap-2">
                            <input readOnly value={newKey} className="w-full rounded-lg border border-amber-200 bg-white px-3 py-2 font-mono text-xs text-gray-800" />
                            <button onClick={() => copy(newKey)} className="rounded-lg border border-amber-200 bg-white p-2 text-amber-700 hover:bg-amber-100">
                                {copied ? <Check className="h-4 w-4 text-green-600" /> : <Copy className="h-4 w-4" />}
                            </button>
                        </div>
                    </div>
                )}

                {/* Lista de chaves */}
                <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    {keys.length === 0 && <p className="px-4 py-8 text-center text-sm text-gray-400">Nenhuma chave criada ainda.</p>}
                    {keys.length > 0 && (
                        <table className="w-full text-sm">
                            <thead className="border-b border-gray-100 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th className="px-4 py-2">Nome</th>
                                    <th className="px-4 py-2">Prefixo</th>
                                    <th className="px-4 py-2">Escopos</th>
                                    <th className="px-4 py-2">Último uso</th>
                                    <th className="px-4 py-2">Status</th>
                                    <th className="px-4 py-2"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {keys.map((k) => (
                                    <tr key={k.id}>
                                        <td className="px-4 py-2 font-medium text-gray-900">{k.name}</td>
                                        <td className="px-4 py-2 font-mono text-xs text-gray-500">{k.key_prefix}…</td>
                                        <td className="px-4 py-2 text-xs text-gray-500">{k.scopes.length} escopo(s)</td>
                                        <td className="px-4 py-2 text-xs text-gray-500">{fmtDate(k.last_used_at)}</td>
                                        <td className="px-4 py-2">
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${k.active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>
                                                {k.active ? 'Ativa' : k.revoked_at ? 'Revogada' : 'Expirada'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {k.active && (
                                                <button onClick={() => revoke(k.id)} className="inline-flex items-center gap-1 rounded-lg border border-red-200 px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-50">
                                                    <Trash2 className="h-3.5 w-3.5" /> Revogar
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>

                {/* Últimas requisições */}
                {logs.length > 0 && (
                    <div className="mt-6 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                        <div className="border-b border-gray-100 p-4">
                            <h2 className="text-sm font-semibold text-gray-900">Últimas requisições</h2>
                        </div>
                        <table className="w-full text-sm">
                            <tbody className="divide-y divide-gray-50">
                                {logs.map((l, i) => (
                                    <tr key={i}>
                                        <td className="px-4 py-2 font-mono text-xs text-gray-700">{l.method}</td>
                                        <td className="px-4 py-2 font-mono text-xs text-gray-500">/{l.path}</td>
                                        <td className="px-4 py-2">
                                            <span className={`text-xs font-semibold ${(l.status_code ?? 0) < 400 ? 'text-green-600' : 'text-red-600'}`}>{l.status_code ?? '—'}</span>
                                        </td>
                                        <td className="px-4 py-2 text-xs text-gray-400">{l.duration_ms ?? '—'} ms</td>
                                        <td className="px-4 py-2 text-xs text-gray-400">{fmtDate(l.created_at)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
