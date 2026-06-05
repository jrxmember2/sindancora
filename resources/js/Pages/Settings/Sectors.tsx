import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { Headset, Plus, Trash2, Pencil, Users, Clock, X } from 'lucide-react';

interface Option { value: string; label: string }
interface DayHours { enabled: boolean; open: string; close: string }
type OfficeHours = Record<string, DayHours>;
interface Sector {
    id: string;
    name: string;
    condominium_id: string;
    condominium: string | null;
    is_active: boolean;
    office_hours: OfficeHours | null;
    away_message: string | null;
    sort_order: number;
    member_ids: string[];
    members: string[];
}
interface Props {
    sectors: Sector[];
    condominiums: Option[];
    users: Option[];
    weekdays: Record<string, string>;
}

const emptyHours = (weekdays: Record<string, string>): OfficeHours =>
    Object.fromEntries(Object.keys(weekdays).map((k) => [k, { enabled: false, open: '08:00', close: '18:00' }]));

export default function Sectors({ sectors, condominiums, users, weekdays }: Props) {
    const [editing, setEditing] = useState<Sector | 'new' | null>(null);
    const [members, setMembers] = useState<Sector | null>(null);

    const remove = (s: Sector) => {
        if (confirm(`Remover o setor "${s.name}"?`)) {
            router.delete(route('sectors.destroy', s.id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout>
            <Head title="Setores de atendimento" />

            <div className="mx-auto max-w-4xl space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900">
                        <Headset className="h-6 w-6 text-blue-600" /> Setores de atendimento
                    </h1>
                    <button onClick={() => setEditing('new')} disabled={condominiums.length === 0} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        <Plus className="h-4 w-4" /> Novo setor
                    </button>
                </div>

                {condominiums.length === 0 && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Cadastre um condomínio antes de criar setores.
                    </div>
                )}

                <p className="text-sm text-gray-500">
                    Setores são os destinos do chatbot (ex.: Portaria, Administração). Cada conversa é encaminhada a um setor, e só os atendentes daquele setor a veem na inbox.
                </p>

                {sectors.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-gray-300 bg-white py-12 text-center text-sm text-gray-400">
                        Nenhum setor ainda.
                    </div>
                ) : (
                    <ul className="space-y-3">
                        {sectors.map((s) => (
                            <li key={s.id} className="rounded-xl border border-gray-200 bg-white p-4">
                                <div className="flex items-center gap-3">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <span className="font-semibold text-gray-900">{s.name}</span>
                                            {!s.is_active && <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">Inativo</span>}
                                        </div>
                                        <p className="mt-0.5 truncate text-sm text-gray-500">
                                            {s.condominium ?? '—'}
                                            {s.members.length > 0 ? ` · ${s.members.length} atendente(s)` : ' · sem atendentes'}
                                        </p>
                                    </div>
                                    <button onClick={() => setMembers(s)} className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        <Users className="h-4 w-4" /> Atendentes
                                    </button>
                                    <button onClick={() => setEditing(s)} className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        <Pencil className="h-4 w-4" /> Editar
                                    </button>
                                    <button onClick={() => remove(s)} className="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {editing && (
                <SectorForm
                    sector={editing === 'new' ? null : editing}
                    condominiums={condominiums}
                    weekdays={weekdays}
                    onClose={() => setEditing(null)}
                />
            )}
            {members && <MembersModal sector={members} users={users} onClose={() => setMembers(null)} />}
        </AppLayout>
    );
}

function SectorForm({ sector, condominiums, weekdays, onClose }: { sector: Sector | null; condominiums: Option[]; weekdays: Record<string, string>; onClose: () => void }) {
    const form = useForm({
        condominium_id: sector?.condominium_id ?? condominiums[0]?.value ?? '',
        name: sector?.name ?? '',
        is_active: sector?.is_active ?? true,
        sort_order: sector?.sort_order ?? 0,
        away_message: sector?.away_message ?? '',
        office_hours: sector?.office_hours ?? emptyHours(weekdays),
    });

    const setDay = (key: string, patch: Partial<DayHours>) =>
        form.setData('office_hours', { ...form.data.office_hours, [key]: { ...form.data.office_hours[key], ...patch } });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: onClose };
        if (sector) form.put(route('sectors.update', sector.id), opts);
        else form.post(route('sectors.store'), opts);
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
            <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6" onClick={(e) => e.stopPropagation()}>
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="font-semibold text-gray-900">{sector ? 'Editar setor' : 'Novo setor'}</h2>
                    <button onClick={onClose} className="rounded p-1 text-gray-400 hover:bg-gray-100"><X className="h-4 w-4" /></button>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Condomínio</label>
                        <select value={form.data.condominium_id} onChange={(e) => form.setData('condominium_id', e.target.value)} className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            {condominiums.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                        </select>
                        {form.errors.condominium_id && <p className="mt-1 text-xs text-red-600">{form.errors.condominium_id}</p>}
                    </div>

                    <div className="flex gap-3">
                        <div className="flex-1">
                            <label className="mb-1 block text-sm font-medium text-gray-700">Nome</label>
                            <input type="text" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Ex.: Portaria" className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" />
                            {form.errors.name && <p className="mt-1 text-xs text-red-600">{form.errors.name}</p>}
                        </div>
                        <div className="w-24">
                            <label className="mb-1 block text-sm font-medium text-gray-700">Ordem</label>
                            <input type="number" min={0} value={form.data.sort_order} onChange={(e) => form.setData('sort_order', Number(e.target.value))} className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" />
                        </div>
                    </div>

                    <label className="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                        Setor ativo (aparece no menu do chatbot)
                    </label>

                    <div>
                        <p className="mb-2 flex items-center gap-1.5 text-sm font-medium text-gray-700"><Clock className="h-4 w-4" /> Horário de atendimento</p>
                        <div className="space-y-1.5">
                            {Object.entries(weekdays).map(([key, label]) => {
                                const day = form.data.office_hours[key] ?? { enabled: false, open: '08:00', close: '18:00' };
                                return (
                                    <div key={key} className="flex items-center gap-2">
                                        <label className="flex w-28 items-center gap-2 text-sm text-gray-600">
                                            <input type="checkbox" checked={day.enabled} onChange={(e) => setDay(key, { enabled: e.target.checked })} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                            {label}
                                        </label>
                                        <input type="time" value={day.open} disabled={!day.enabled} onChange={(e) => setDay(key, { open: e.target.value })} className="rounded-lg border-gray-300 text-sm disabled:bg-gray-100 disabled:text-gray-400" />
                                        <span className="text-gray-400">até</span>
                                        <input type="time" value={day.close} disabled={!day.enabled} onChange={(e) => setDay(key, { close: e.target.value })} className="rounded-lg border-gray-300 text-sm disabled:bg-gray-100 disabled:text-gray-400" />
                                    </div>
                                );
                            })}
                        </div>
                        <p className="mt-1 text-xs text-gray-400">Sem nenhum dia marcado, o setor é tratado como sempre disponível.</p>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Mensagem de fora de expediente</label>
                        <textarea value={form.data.away_message} onChange={(e) => form.setData('away_message', e.target.value)} rows={2} placeholder="Ex.: Nosso atendimento funciona de seg a sex, 8h às 18h. Retornaremos em breve." className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" />
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" onClick={onClose} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                        <button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function MembersModal({ sector, users, onClose }: { sector: Sector; users: Option[]; onClose: () => void }) {
    const form = useForm<{ user_ids: string[] }>({ user_ids: sector.member_ids });

    const toggle = (id: string) => {
        const set = new Set(form.data.user_ids);
        set.has(id) ? set.delete(id) : set.add(id);
        form.setData('user_ids', Array.from(set));
    };

    const save = () => form.put(route('sectors.members', sector.id), { preserveScroll: true, onSuccess: onClose });

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={onClose}>
            <div className="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl bg-white p-6" onClick={(e) => e.stopPropagation()}>
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="font-semibold text-gray-900">Atendentes — {sector.name}</h2>
                    <button onClick={onClose} className="rounded p-1 text-gray-400 hover:bg-gray-100"><X className="h-4 w-4" /></button>
                </div>

                {users.length === 0 ? (
                    <p className="py-6 text-center text-sm text-gray-400">Nenhum usuário de painel disponível.</p>
                ) : (
                    <div className="space-y-1.5">
                        {users.map((u) => (
                            <label key={u.value} className="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                                <input type="checkbox" checked={form.data.user_ids.includes(u.value)} onChange={() => toggle(u.value)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                {u.label}
                            </label>
                        ))}
                    </div>
                )}

                <div className="mt-4 flex justify-end gap-2">
                    <button onClick={onClose} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button onClick={save} disabled={form.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Salvar</button>
                </div>
            </div>
        </div>
    );
}
