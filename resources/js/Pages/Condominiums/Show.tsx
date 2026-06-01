import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Building2, Grid3X3, Users, Plus, Pencil, Trash2, X, MapPin, Phone, Mail } from 'lucide-react';
import { useState } from 'react';

interface Block { id: string; name: string; floors: number | null; units_count: number }
interface Manager { id: string; role: string; start_date: string; person: { id: string; name: string } }
interface Person { id: string; name: string; cpf: string | null }
interface Condominium {
    id: string; name: string; cnpj: string | null; email: string | null; phone: string | null;
    street: string | null; number: string | null; complement: string | null;
    neighborhood: string | null; city: string | null; state: string | null; zip_code: string | null;
    status: string; blocks: Block[]; active_managers: Manager[];
}
interface Props {
    condominium: Condominium;
    unitStats: Record<string, number>;
    persons: Person[];
    managerRoles: Record<string, string>;
}

function BlockModal({ condo, onClose }: { condo: Condominium; onClose: () => void }) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', floors: '' });
    const submit = () => post(route('condominiums.blocks.store', condo.id), { onSuccess: () => { reset(); onClose(); } });
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-sm rounded-2xl bg-white shadow-xl p-6 space-y-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-base font-semibold text-gray-900">Adicionar Bloco</h2>
                    <button onClick={onClose}><X className="h-5 w-5 text-gray-400 hover:text-gray-600" /></button>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Nome do Bloco *</label>
                    <input value={data.name} onChange={e => setData('name', e.target.value)} placeholder="Ex: Bloco A" className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                    {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Número de Andares</label>
                    <input type="number" value={data.floors} onChange={e => setData('floors', e.target.value)} min={1} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                </div>
                <div className="flex gap-2 pt-2">
                    <button onClick={onClose} className="flex-1 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button onClick={submit} disabled={processing || !data.name} className="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        {processing ? 'Salvando…' : 'Adicionar'}
                    </button>
                </div>
            </div>
        </div>
    );
}

function ManagerModal({ condo, persons, roles, onClose }: { condo: Condominium; persons: Person[]; roles: Record<string, string>; onClose: () => void }) {
    const { data, setData, post, processing, errors, reset } = useForm({ person_id: '', role: 'sindico', start_date: new Date().toISOString().slice(0, 10) });
    const submit = () => post(route('condominiums.managers.store', condo.id), { onSuccess: () => { reset(); onClose(); } });
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="w-full max-w-sm rounded-2xl bg-white shadow-xl p-6 space-y-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-base font-semibold text-gray-900">Adicionar Gestor</h2>
                    <button onClick={onClose}><X className="h-5 w-5 text-gray-400 hover:text-gray-600" /></button>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Pessoa *</label>
                    <select value={data.person_id} onChange={e => setData('person_id', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="">Selecione…</option>
                        {persons.map(p => <option key={p.id} value={p.id}>{p.name}{p.cpf ? ` — ${p.cpf}` : ''}</option>)}
                    </select>
                    {errors.person_id && <p className="mt-1 text-xs text-red-600">{errors.person_id}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Cargo *</label>
                    <select value={data.role} onChange={e => setData('role', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        {Object.entries(roles).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Início do Mandato *</label>
                    <input type="date" value={data.start_date} onChange={e => setData('start_date', e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                </div>
                <div className="flex gap-2 pt-2">
                    <button onClick={onClose} className="flex-1 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button onClick={submit} disabled={processing || !data.person_id} className="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        {processing ? 'Salvando…' : 'Adicionar'}
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function CondominiumShow({ condominium, unitStats, persons, managerRoles }: Props) {
    const [blockModal, setBlockModal] = useState(false);
    const [managerModal, setManagerModal] = useState(false);

    const totalUnits = Object.values(unitStats).reduce((a, b) => a + b, 0);

    const destroyBlock = (blockId: string) => {
        if (confirm('Excluir este bloco?')) router.delete(route('condominiums.blocks.destroy', [condominium.id, blockId]));
    };
    const destroyManager = (managerId: string) => {
        if (confirm('Encerrar mandato deste gestor?')) router.delete(route('condominiums.managers.destroy', [condominium.id, managerId]));
    };

    return (
        <AppLayout>
            <Head title={condominium.name} />

            {blockModal && <BlockModal condo={condominium} onClose={() => setBlockModal(false)} />}
            {managerModal && <ManagerModal condo={condominium} persons={persons} roles={managerRoles} onClose={() => setManagerModal(false)} />}

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <Link href={route('condominiums.index')} className="text-sm text-gray-500 hover:text-gray-700">← Condomínios</Link>
                        <h1 className="mt-1 text-2xl font-bold text-gray-900">{condominium.name}</h1>
                        {condominium.city && (
                            <p className="mt-1 flex items-center gap-1 text-sm text-gray-500">
                                <MapPin className="h-3.5 w-3.5" />
                                {[condominium.city, condominium.state].filter(Boolean).join(' – ')}
                            </p>
                        )}
                    </div>
                    <Link href={route('condominiums.edit', condominium.id)} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        <Pencil className="h-4 w-4" /> Editar
                    </Link>
                </div>

                {/* Info + stats */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div className="rounded-xl bg-white border border-gray-100 shadow-sm p-4 flex items-center gap-3">
                        <Grid3X3 className="h-8 w-8 text-blue-600" />
                        <div><p className="text-2xl font-bold text-gray-900">{condominium.blocks.length}</p><p className="text-xs text-gray-500">Blocos</p></div>
                    </div>
                    <div className="rounded-xl bg-white border border-gray-100 shadow-sm p-4 flex items-center gap-3">
                        <Building2 className="h-8 w-8 text-indigo-600" />
                        <div><p className="text-2xl font-bold text-gray-900">{totalUnits}</p><p className="text-xs text-gray-500">Unidades</p></div>
                    </div>
                    <div className="rounded-xl bg-white border border-gray-100 shadow-sm p-4 flex items-center gap-3">
                        <Users className="h-8 w-8 text-violet-600" />
                        <div><p className="text-2xl font-bold text-gray-900">{unitStats['occupied'] ?? 0}</p><p className="text-xs text-gray-500">Ocupadas</p></div>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Blocos */}
                    <div className="lg:col-span-2 space-y-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-base font-semibold text-gray-900">Blocos e Torres</h2>
                            <div className="flex gap-2">
                                <button onClick={() => setBlockModal(true)} className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                    <Plus className="h-3.5 w-3.5" /> Adicionar
                                </button>
                                <Link href={route('condominiums.units.index', condominium.id)} className="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 transition-colors">
                                    <Building2 className="h-3.5 w-3.5" /> Ver Unidades
                                </Link>
                            </div>
                        </div>

                        {condominium.blocks.length === 0 ? (
                            <div className="rounded-xl border-2 border-dashed border-gray-200 p-8 text-center">
                                <Grid3X3 className="mx-auto h-10 w-10 text-gray-300" />
                                <p className="mt-3 text-sm text-gray-500">Nenhum bloco cadastrado.</p>
                                <button onClick={() => setBlockModal(true)} className="mt-3 inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                                    <Plus className="h-3.5 w-3.5" /> Adicionar bloco
                                </button>
                            </div>
                        ) : (
                            <div className="grid gap-3 sm:grid-cols-2">
                                {condominium.blocks.map(block => (
                                    <div key={block.id} className="rounded-xl bg-white border border-gray-100 shadow-sm p-4 flex items-center justify-between">
                                        <div>
                                            <p className="font-semibold text-gray-900 text-sm">{block.name}</p>
                                            <p className="text-xs text-gray-500 mt-0.5">
                                                {block.units_count} unidades{block.floors ? ` · ${block.floors} andares` : ''}
                                            </p>
                                        </div>
                                        <button onClick={() => destroyBlock(block.id)} className="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-500 transition-colors">
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Gestores + Dados */}
                    <div className="space-y-4">
                        {/* Dados */}
                        <div className="rounded-xl bg-white border border-gray-100 shadow-sm p-4 space-y-2">
                            <h3 className="text-sm font-semibold text-gray-900">Dados</h3>
                            {condominium.cnpj && <p className="text-xs text-gray-600">CNPJ: {condominium.cnpj}</p>}
                            {condominium.email && <p className="flex items-center gap-1.5 text-xs text-gray-600"><Mail className="h-3 w-3" />{condominium.email}</p>}
                            {condominium.phone && <p className="flex items-center gap-1.5 text-xs text-gray-600"><Phone className="h-3 w-3" />{condominium.phone}</p>}
                            {condominium.street && (
                                <p className="flex items-start gap-1.5 text-xs text-gray-600">
                                    <MapPin className="h-3 w-3 mt-0.5 shrink-0" />
                                    <span>{[condominium.street, condominium.number, condominium.complement].filter(Boolean).join(', ')}, {condominium.neighborhood} — {condominium.city}/{condominium.state}</span>
                                </p>
                            )}
                        </div>

                        {/* Gestores */}
                        <div className="rounded-xl bg-white border border-gray-100 shadow-sm p-4 space-y-3">
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-semibold text-gray-900">Gestores</h3>
                                <button onClick={() => setManagerModal(true)} className="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-700">
                                    <Plus className="h-3.5 w-3.5" /> Adicionar
                                </button>
                            </div>
                            {condominium.active_managers.length === 0 ? (
                                <p className="text-xs text-gray-500">Nenhum gestor cadastrado.</p>
                            ) : (
                                <div className="space-y-2">
                                    {condominium.active_managers.map(m => (
                                        <div key={m.id} className="flex items-center justify-between">
                                            <div>
                                                <p className="text-xs font-medium text-gray-900">{m.person.name}</p>
                                                <p className="text-xs text-gray-500">{managerRoles[m.role]} · desde {new Date(m.start_date).toLocaleDateString('pt-BR')}</p>
                                            </div>
                                            <button onClick={() => destroyManager(m.id)} className="rounded p-1 text-gray-400 hover:text-red-500 transition-colors">
                                                <X className="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
