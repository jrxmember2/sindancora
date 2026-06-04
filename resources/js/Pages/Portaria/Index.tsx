import { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import PortariaLayout from '@/Layouts/PortariaLayout';
import { QrCode, LogOut, UserPlus, DoorOpen } from 'lucide-react';

interface UnitOption {
    id: string;
    number: string;
}
interface CondominiumOption {
    id: string;
    name: string;
    units: UnitOption[];
}
interface PresentVisit {
    id: string;
    visitor_name: string;
    visitor_document: string | null;
    condominium: string | null;
    unit: string | null;
    check_in_at: string | null;
    check_out_at: string | null;
}

function formatTime(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

export default function Index({ present, condominiums }: { present: PresentVisit[]; condominiums: CondominiumOption[] }) {
    const [showWalkIn, setShowWalkIn] = useState(false);

    const form = useForm({
        condominium_id: '',
        unit_id: '',
        visitor_name: '',
        visitor_document: '',
        notes: '',
    });

    const checkout = useForm({});

    const selectedCondo = condominiums.find((c) => c.id === form.data.condominium_id);

    const submitWalkIn = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('portaria.checkin.walkin'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setShowWalkIn(false);
            },
        });
    };

    return (
        <PortariaLayout title="Portaria">
            {/* Ações principais */}
            <div className="mb-6 grid gap-3 sm:grid-cols-2">
                <Link
                    href={route('portaria.validate')}
                    className="flex items-center gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4 transition-colors hover:bg-blue-100"
                >
                    <span className="flex h-11 w-11 items-center justify-center rounded-lg bg-blue-600 text-white">
                        <QrCode className="h-6 w-6" />
                    </span>
                    <span>
                        <span className="block font-semibold text-gray-900">Validar QR / código</span>
                        <span className="block text-sm text-gray-500">Visitante pré-autorizado pelo morador</span>
                    </span>
                </Link>

                <button
                    onClick={() => setShowWalkIn((s) => !s)}
                    className="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 text-left transition-colors hover:bg-gray-50"
                >
                    <span className="flex h-11 w-11 items-center justify-center rounded-lg bg-gray-700 text-white">
                        <UserPlus className="h-6 w-6" />
                    </span>
                    <span>
                        <span className="block font-semibold text-gray-900">Registrar visitante</span>
                        <span className="block text-sm text-gray-500">Entrada avulsa (sem autorização prévia)</span>
                    </span>
                </button>
            </div>

            {/* Form de walk-in */}
            {showWalkIn && (
                <form onSubmit={submitWalkIn} className="mb-6 space-y-4 rounded-xl border border-gray-200 bg-white p-5">
                    <h2 className="font-semibold text-gray-900">Nova entrada avulsa</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Condomínio *</label>
                            <select
                                value={form.data.condominium_id}
                                onChange={(e) => form.setData({ ...form.data, condominium_id: e.target.value, unit_id: '' })}
                                className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                                <option value="">Selecione…</option>
                                {condominiums.map((c) => (
                                    <option key={c.id} value={c.id}>{c.name}</option>
                                ))}
                            </select>
                            {form.errors.condominium_id && <p className="mt-1 text-xs text-red-600">{form.errors.condominium_id}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Unidade (opcional)</label>
                            <select
                                value={form.data.unit_id}
                                onChange={(e) => form.setData('unit_id', e.target.value)}
                                disabled={!selectedCondo}
                                className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-50"
                            >
                                <option value="">—</option>
                                {selectedCondo?.units.map((u) => (
                                    <option key={u.id} value={u.id}>{u.number}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Nome do visitante *</label>
                            <input
                                type="text"
                                value={form.data.visitor_name}
                                onChange={(e) => form.setData('visitor_name', e.target.value)}
                                className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                            {form.errors.visitor_name && <p className="mt-1 text-xs text-red-600">{form.errors.visitor_name}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Documento (opcional)</label>
                            <input
                                type="text"
                                value={form.data.visitor_document}
                                onChange={(e) => form.setData('visitor_document', e.target.value)}
                                className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                            Registrar entrada
                        </button>
                        <button type="button" onClick={() => setShowWalkIn(false)} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </button>
                    </div>
                </form>
            )}

            {/* Visitantes presentes */}
            <section className="rounded-xl border border-gray-200 bg-white">
                <div className="flex items-center gap-2 border-b border-gray-100 px-5 py-3">
                    <DoorOpen className="h-5 w-5 text-gray-400" />
                    <h2 className="font-semibold text-gray-900">Visitantes presentes</h2>
                    <span className="ml-auto rounded-full bg-gray-100 px-2.5 py-0.5 text-sm font-medium text-gray-600">{present.length}</span>
                </div>

                {present.length === 0 ? (
                    <p className="px-5 py-8 text-center text-sm text-gray-400">Nenhum visitante no momento.</p>
                ) : (
                    <ul className="divide-y divide-gray-100">
                        {present.map((v) => (
                            <li key={v.id} className="flex items-center gap-3 px-5 py-3">
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium text-gray-900">{v.visitor_name}</p>
                                    <p className="truncate text-sm text-gray-500">
                                        {v.condominium}{v.unit ? ` · Un. ${v.unit}` : ''} · entrada {formatTime(v.check_in_at)}
                                    </p>
                                </div>
                                <button
                                    onClick={() => checkout.post(route('portaria.checkout', v.id), { preserveScroll: true })}
                                    disabled={checkout.processing}
                                    className="flex flex-shrink-0 items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                                >
                                    <LogOut className="h-4 w-4" /> Saída
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </PortariaLayout>
    );
}
