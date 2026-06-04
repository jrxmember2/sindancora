import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { DoorOpen, Plus, Trash2, KeyRound } from 'lucide-react';

interface UnitOption { id: string; number: string }
interface CondominiumOption { id: string; name: string; units: UnitOption[] }
interface Visit {
    id: string; visitor_name: string; visitor_document: string | null;
    condominium: string | null; unit: string | null; check_in_at: string | null; check_out_at: string | null;
}
interface Authorization {
    id: string; visitor_name: string; visitor_document: string | null;
    type: string; type_label: string; status: string; status_label: string; token: string;
    condominium: string | null; unit: string | null; valid_from: string | null; valid_until: string | null; created_at: string | null;
}
interface Props {
    present: Visit[];
    recent: Visit[];
    authorizations: Authorization[];
    condominiums: CondominiumOption[];
    canManage: boolean;
}

const statusStyles: Record<string, string> = {
    active: 'bg-green-100 text-green-700',
    used: 'bg-gray-100 text-gray-600',
    expired: 'bg-amber-100 text-amber-700',
    revoked: 'bg-red-100 text-red-700',
};

function formatTime(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

export default function GatehouseIndex({ present, recent, authorizations, condominiums, canManage }: Props) {
    const [showForm, setShowForm] = useState(false);

    const form = useForm({
        condominium_id: '', unit_id: '', visitor_name: '', visitor_document: '',
        visitor_phone: '', type: 'single', valid_from: '', valid_until: '', notes: '',
    });

    const selectedCondo = condominiums.find((c) => c.id === form.data.condominium_id);
    const field = 'w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500';

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('gatehouse.authorizations.store'), {
            preserveScroll: true,
            onSuccess: () => { form.reset(); setShowForm(false); },
        });
    };

    const revoke = (id: string) => {
        if (confirm('Revogar esta autorização?')) {
            router.delete(route('gatehouse.authorizations.revoke', id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout>
            <Head title="Portaria" />

            <div className="mb-4 flex items-center justify-between">
                <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900"><DoorOpen className="h-6 w-6" /> Portaria</h1>
                {canManage && (
                    <button onClick={() => setShowForm((s) => !s)} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        <Plus className="h-4 w-4" /> Autorizar visitante
                    </button>
                )}
            </div>

            {/* Form de autorização */}
            {showForm && canManage && (
                <form onSubmit={submit} className="mb-6 space-y-4 rounded-xl border border-gray-200 bg-white p-5">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Condomínio *</label>
                            <select value={form.data.condominium_id} onChange={(e) => form.setData({ ...form.data, condominium_id: e.target.value, unit_id: '' })} className={field}>
                                <option value="">Selecione…</option>
                                {condominiums.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                            {form.errors.condominium_id && <p className="mt-1 text-xs text-red-600">{form.errors.condominium_id}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Unidade *</label>
                            <select value={form.data.unit_id} onChange={(e) => form.setData('unit_id', e.target.value)} disabled={!selectedCondo} className={`${field} disabled:bg-gray-50`}>
                                <option value="">Selecione…</option>
                                {selectedCondo?.units.map((u) => <option key={u.id} value={u.id}>{u.number}</option>)}
                            </select>
                            {form.errors.unit_id && <p className="mt-1 text-xs text-red-600">{form.errors.unit_id}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Tipo</label>
                            <select value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} className={field}>
                                <option value="single">Visita única</option>
                                <option value="recurring">Recorrente</option>
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Nome do visitante *</label>
                            <input type="text" value={form.data.visitor_name} onChange={(e) => form.setData('visitor_name', e.target.value)} className={field} />
                            {form.errors.visitor_name && <p className="mt-1 text-xs text-red-600">{form.errors.visitor_name}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Documento</label>
                            <input type="text" value={form.data.visitor_document} onChange={(e) => form.setData('visitor_document', e.target.value)} className={field} />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Telefone</label>
                            <input type="text" value={form.data.visitor_phone} onChange={(e) => form.setData('visitor_phone', e.target.value)} className={field} />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Válida a partir de</label>
                            <input type="date" value={form.data.valid_from} onChange={(e) => form.setData('valid_from', e.target.value)} className={field} />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Válida até</label>
                            <input type="date" value={form.data.valid_until} onChange={(e) => form.setData('valid_until', e.target.value)} className={field} />
                            {form.errors.valid_until && <p className="mt-1 text-xs text-red-600">{form.errors.valid_until}</p>}
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Criar autorização</button>
                        <button type="button" onClick={() => setShowForm(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                    </div>
                </form>
            )}

            {/* Visitantes presentes */}
            <section className="mb-6 rounded-xl border border-gray-200 bg-white">
                <div className="flex items-center gap-2 border-b border-gray-100 px-5 py-3">
                    <h2 className="font-semibold text-gray-900">Visitantes presentes</h2>
                    <span className="ml-auto rounded-full bg-gray-100 px-2.5 py-0.5 text-sm font-medium text-gray-600">{present.length}</span>
                </div>
                {present.length === 0 ? (
                    <p className="px-5 py-6 text-center text-sm text-gray-400">Nenhum visitante no momento.</p>
                ) : (
                    <ul className="divide-y divide-gray-100">
                        {present.map((v) => (
                            <li key={v.id} className="flex items-center gap-3 px-5 py-3 text-sm">
                                <span className="min-w-0 flex-1">
                                    <span className="block font-medium text-gray-900">{v.visitor_name}</span>
                                    <span className="block text-gray-500">{v.condominium}{v.unit ? ` · Un. ${v.unit}` : ''} · entrada {formatTime(v.check_in_at)}</span>
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            {/* Autorizações */}
            <section className="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white">
                <div className="border-b border-gray-100 px-5 py-3"><h2 className="font-semibold text-gray-900">Autorizações</h2></div>
                <table className="min-w-full divide-y divide-gray-100 text-sm">
                    <thead className="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th className="px-4 py-3">Visitante</th>
                            <th className="px-4 py-3">Local</th>
                            <th className="px-4 py-3">Código</th>
                            <th className="px-4 py-3">Status</th>
                            {canManage && <th className="px-4 py-3"></th>}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {authorizations.length === 0 && (
                            <tr><td colSpan={canManage ? 5 : 4} className="px-4 py-6 text-center text-gray-400">Nenhuma autorização.</td></tr>
                        )}
                        {authorizations.map((a) => (
                            <tr key={a.id}>
                                <td className="px-4 py-3">
                                    <span className="font-medium text-gray-900">{a.visitor_name}</span>
                                    <span className="block text-xs text-gray-400">{a.type_label}</span>
                                </td>
                                <td className="px-4 py-3 text-gray-600">{a.condominium}{a.unit ? ` · Un. ${a.unit}` : ''}</td>
                                <td className="px-4 py-3"><span className="inline-flex items-center gap-1 font-mono text-gray-700"><KeyRound className="h-3.5 w-3.5 text-gray-400" />{a.token}</span></td>
                                <td className="px-4 py-3"><span className={`rounded-full px-2 py-0.5 text-xs font-medium ${statusStyles[a.status] ?? 'bg-gray-100 text-gray-600'}`}>{a.status_label}</span></td>
                                {canManage && (
                                    <td className="px-4 py-3 text-right">
                                        {a.status === 'active' && (
                                            <button onClick={() => revoke(a.id)} className="inline-flex items-center gap-1 text-sm text-red-600 hover:text-red-700">
                                                <Trash2 className="h-4 w-4" /> Revogar
                                            </button>
                                        )}
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>

            {/* Histórico recente */}
            <section className="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <div className="border-b border-gray-100 px-5 py-3"><h2 className="font-semibold text-gray-900">Acessos recentes</h2></div>
                <table className="min-w-full divide-y divide-gray-100 text-sm">
                    <thead className="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th className="px-4 py-3">Visitante</th>
                            <th className="px-4 py-3">Local</th>
                            <th className="px-4 py-3">Entrada</th>
                            <th className="px-4 py-3">Saída</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {recent.length === 0 && (
                            <tr><td colSpan={4} className="px-4 py-6 text-center text-gray-400">Nenhum acesso registrado.</td></tr>
                        )}
                        {recent.map((v) => (
                            <tr key={v.id}>
                                <td className="px-4 py-3 font-medium text-gray-900">{v.visitor_name}</td>
                                <td className="px-4 py-3 text-gray-600">{v.condominium}{v.unit ? ` · Un. ${v.unit}` : ''}</td>
                                <td className="px-4 py-3 text-gray-600">{formatTime(v.check_in_at)}</td>
                                <td className="px-4 py-3">
                                    {v.check_out_at ? <span className="text-gray-600">{formatTime(v.check_out_at)}</span> : <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Presente</span>}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>
        </AppLayout>
    );
}
