import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { User, MapPin, Phone, Mail, Building2, Plus, X, Calendar, KeyRound, Send } from 'lucide-react';
import { useState } from 'react';
import { maskCpfCnpj } from '@/lib/masks';

interface UnitLink {
    id: string; type: string; is_primary: boolean; start_date: string; end_date: string | null;
    unit: { id: string; number: string; type: string; block: { name: string } | null; condominium: { id: string; name: string } };
}
interface AvailableUnit {
    id: string; number: string; type: string;
    condominium: { id: string; name: string }; block: { name: string } | null;
}
interface Person {
    id: string; name: string; cpf: string | null; email: string | null; phone: string | null;
    phone2: string | null; birth_date: string | null; city: string | null; state: string | null;
    street: string | null; number: string | null; complement: string | null; neighborhood: string | null;
    notes: string | null; unit_links: UnitLink[];
    user: { id: string; email: string; status: string } | null;
}
interface Props { person: Person; linkTypes: Record<string, string>; availableUnits: AvailableUnit[] }

function LinkModal({ person, availableUnits, linkTypes, onClose }: { person: Person; availableUnits: AvailableUnit[]; linkTypes: Record<string, string>; onClose: () => void }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        unit_id: '', type: 'resident', is_primary: false as boolean,
        start_date: new Date().toISOString().slice(0, 10),
    });
    const submit = () => post(route('persons.links.store', person.id), { onSuccess: () => { reset(); onClose(); } });

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-md rounded-2xl bg-white shadow-xl p-6 space-y-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-base font-semibold text-gray-900">Vincular à Unidade</h2>
                    <button onClick={onClose}><X className="h-5 w-5 text-gray-400 hover:text-gray-600" /></button>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Unidade *</label>
                    <select value={data.unit_id} onChange={e => setData('unit_id', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="">Selecione…</option>
                        {availableUnits.map(u => (
                            <option key={u.id} value={u.id}>
                                {u.condominium.name} · {u.block ? `${u.block.name} · ` : ''}{u.number}
                            </option>
                        ))}
                    </select>
                    {errors.unit_id && <p className="mt-1 text-xs text-red-600">{errors.unit_id}</p>}
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Tipo *</label>
                        <select value={data.type} onChange={e => setData('type', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                            {Object.entries(linkTypes).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Início *</label>
                        <input type="date" value={data.start_date} onChange={e => setData('start_date', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                    </div>
                </div>
                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={data.is_primary} onChange={e => setData('is_primary', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                    Morador principal da unidade
                </label>
                <div className="flex gap-2 pt-2">
                    <button onClick={onClose} className="flex-1 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button onClick={submit} disabled={processing || !data.unit_id} className="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        {processing ? 'Salvando…' : 'Vincular'}
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function PersonShow({ person, linkTypes, availableUnits }: Props) {
    const [linkModal, setLinkModal] = useState(false);
    const [inviting, setInviting] = useState(false);

    const endLink = (linkId: string) => {
        if (confirm('Encerrar este vínculo?')) router.delete(route('persons.links.destroy', [person.id, linkId]));
    };

    const sendInvite = () => {
        const isResend = person.user?.status === 'invited';
        if (!confirm(isResend ? 'Reenviar o convite de acesso ao portal?' : `Enviar convite de acesso ao portal para ${person.email}?`)) return;
        router.post(route('persons.invite', person.id), {}, {
            preserveScroll: true,
            onStart: () => setInviting(true),
            onFinish: () => setInviting(false),
        });
    };

    const portalStatus = person.user?.status; // 'active' | 'invited' | undefined

    const activeLinks = person.unit_links.filter(l => !l.end_date);
    const historyLinks = person.unit_links.filter(l => l.end_date);

    return (
        <AppLayout>
            <Head title={person.name} />
            {linkModal && <LinkModal person={person} availableUnits={availableUnits} linkTypes={linkTypes} onClose={() => setLinkModal(false)} />}

            <div className="space-y-6">
                <div className="flex items-start justify-between">
                    <div>
                        <Link href={route('persons.index')} className="text-sm text-gray-500 hover:text-gray-700">← Pessoas</Link>
                        <h1 className="mt-1 text-2xl font-bold text-gray-900">{person.name}</h1>
                        {person.cpf && <p className="text-sm text-gray-500 font-mono">{maskCpfCnpj(person.cpf)}</p>}
                    </div>
                    <Link href={route('persons.edit', person.id)} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        Editar
                    </Link>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Dados */}
                    <div className="space-y-4">
                        <div className="rounded-xl bg-white border border-gray-100 shadow-sm p-4 space-y-3">
                            <div className="flex items-center gap-2">
                                <User className="h-4 w-4 text-gray-400" />
                                <span className="text-sm font-semibold text-gray-700">Dados</span>
                            </div>
                            {person.birth_date && (
                                <div className="flex items-center gap-2 text-sm text-gray-600">
                                    <Calendar className="h-3.5 w-3.5 text-gray-400" />
                                    {new Date(person.birth_date).toLocaleDateString('pt-BR')}
                                </div>
                            )}
                            {person.email && <div className="flex items-center gap-2 text-sm text-gray-600"><Mail className="h-3.5 w-3.5 text-gray-400" />{person.email}</div>}
                            {person.phone && <div className="flex items-center gap-2 text-sm text-gray-600"><Phone className="h-3.5 w-3.5 text-gray-400" />{person.phone}</div>}
                            {person.phone2 && <div className="flex items-center gap-2 text-sm text-gray-600"><Phone className="h-3.5 w-3.5 text-gray-400" />{person.phone2}</div>}
                            {person.street && (
                                <div className="flex items-start gap-2 text-sm text-gray-600">
                                    <MapPin className="h-3.5 w-3.5 text-gray-400 mt-0.5 shrink-0" />
                                    <span>{[person.street, person.number, person.complement].filter(Boolean).join(', ')}, {person.neighborhood} — {person.city}/{person.state}</span>
                                </div>
                            )}
                            {person.notes && <p className="text-xs text-gray-500 border-t border-gray-100 pt-2">{person.notes}</p>}
                        </div>

                        {/* Acesso ao portal do morador */}
                        <div className="rounded-xl bg-white border border-gray-100 shadow-sm p-4 space-y-3">
                            <div className="flex items-center gap-2">
                                <KeyRound className="h-4 w-4 text-gray-400" />
                                <span className="text-sm font-semibold text-gray-700">Acesso ao portal</span>
                            </div>

                            {portalStatus === 'active' && (
                                <p className="flex items-center gap-2 text-sm text-green-700">
                                    <span className="h-2 w-2 rounded-full bg-green-500" /> Acesso ativo
                                </p>
                            )}
                            {portalStatus === 'invited' && (
                                <p className="flex items-center gap-2 text-sm text-amber-700">
                                    <span className="h-2 w-2 rounded-full bg-amber-500" /> Convite enviado — aguardando ativação
                                </p>
                            )}
                            {!portalStatus && (
                                <p className="text-sm text-gray-500">Esta pessoa ainda não tem acesso ao portal.</p>
                            )}

                            {portalStatus !== 'active' && (
                                person.email ? (
                                    <button
                                        onClick={sendInvite}
                                        disabled={inviting}
                                        className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                                    >
                                        <Send className="h-3.5 w-3.5" />
                                        {inviting ? 'Enviando…' : portalStatus === 'invited' ? 'Reenviar convite' : 'Convidar para o portal'}
                                    </button>
                                ) : (
                                    <p className="text-xs text-gray-400">Cadastre um e-mail para poder convidar.</p>
                                )
                            )}
                        </div>
                    </div>

                    {/* Vínculos */}
                    <div className="lg:col-span-2 space-y-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-base font-semibold text-gray-900">Vínculos com Unidades</h2>
                            <button onClick={() => setLinkModal(true)} className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                <Plus className="h-3.5 w-3.5" /> Vincular
                            </button>
                        </div>

                        {/* Ativos */}
                        {activeLinks.length > 0 && (
                            <div className="space-y-2">
                                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide">Ativos</p>
                                {activeLinks.map(link => (
                                    <div key={link.id} className="flex items-center justify-between rounded-xl bg-white border border-gray-100 shadow-sm px-4 py-3">
                                        <div className="flex items-center gap-3">
                                            <Building2 className="h-4 w-4 text-blue-500 shrink-0" />
                                            <div>
                                                <p className="text-sm font-medium text-gray-900">
                                                    {link.unit.condominium.name} · {link.unit.block ? `${link.unit.block.name} · ` : ''}{link.unit.number}
                                                    {link.is_primary && <span className="ml-2 rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">Principal</span>}
                                                </p>
                                                <p className="text-xs text-gray-500">{linkTypes[link.type]} · desde {new Date(link.start_date).toLocaleDateString('pt-BR')}</p>
                                            </div>
                                        </div>
                                        <button onClick={() => endLink(link.id)} className="rounded p-1 text-gray-400 hover:text-red-500 transition-colors" title="Encerrar vínculo">
                                            <X className="h-4 w-4" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}

                        {activeLinks.length === 0 && (
                            <div className="rounded-xl border-2 border-dashed border-gray-200 p-8 text-center">
                                <Building2 className="mx-auto h-8 w-8 text-gray-300" />
                                <p className="mt-2 text-sm text-gray-500">Sem vínculos ativos.</p>
                                <button onClick={() => setLinkModal(true)} className="mt-2 text-sm text-blue-600 hover:text-blue-700">Vincular a uma unidade</button>
                            </div>
                        )}

                        {/* Histórico */}
                        {historyLinks.length > 0 && (
                            <div className="space-y-2">
                                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide">Histórico</p>
                                {historyLinks.map(link => (
                                    <div key={link.id} className="flex items-center gap-3 rounded-xl bg-gray-50 border border-gray-100 px-4 py-3 opacity-70">
                                        <Building2 className="h-4 w-4 text-gray-400 shrink-0" />
                                        <div>
                                            <p className="text-sm text-gray-700">{link.unit.condominium.name} · {link.unit.number}</p>
                                            <p className="text-xs text-gray-500">
                                                {linkTypes[link.type]} · {new Date(link.start_date).toLocaleDateString('pt-BR')} → {new Date(link.end_date!).toLocaleDateString('pt-BR')}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
