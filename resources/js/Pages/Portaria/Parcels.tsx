import { useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import PortariaLayout from '@/Layouts/PortariaLayout';
import { Package, Plus, Check } from 'lucide-react';

interface UnitOption { id: string; number: string }
interface CondominiumOption { id: string; name: string; units: UnitOption[] }
interface ParcelRow {
    id: string;
    description: string;
    carrier: string | null;
    tracking_code: string | null;
    status: 'awaiting' | 'picked_up';
    condominium: string | null;
    unit: string | null;
    received_at: string | null;
    picked_up_at: string | null;
    photo: string | null;
}

function formatTime(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

export default function Parcels({ parcels, condominiums, statuses }: { parcels: ParcelRow[]; condominiums: CondominiumOption[]; statuses: Record<string, string> }) {
    const [showForm, setShowForm] = useState(false);

    const form = useForm({
        condominium_id: '',
        unit_id: '',
        description: '',
        carrier: '',
        tracking_code: '',
        notes: '',
        photo: null as File | null,
    });

    const selectedCondo = condominiums.find((c) => c.id === form.data.condominium_id);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('portaria.parcels.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { form.reset(); setShowForm(false); },
        });
    };

    const pickup = (id: string) => router.post(route('portaria.parcels.pickup', id), {}, { preserveScroll: true });

    const input = 'w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500';

    return (
        <PortariaLayout title="Encomendas">
            <button
                onClick={() => setShowForm((s) => !s)}
                className="mb-5 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
            >
                <Plus className="h-4 w-4" /> Registrar encomenda
            </button>

            {showForm && (
                <form onSubmit={submit} className="mb-6 space-y-3 rounded-xl border border-gray-200 bg-white p-4">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <select value={form.data.condominium_id} onChange={(e) => { form.setData('condominium_id', e.target.value); form.setData('unit_id', ''); }} className={input}>
                            <option value="">Condomínio…</option>
                            {condominiums.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                        <select value={form.data.unit_id} onChange={(e) => form.setData('unit_id', e.target.value)} className={input} disabled={!selectedCondo}>
                            <option value="">Unidade…</option>
                            {selectedCondo?.units.map((u) => <option key={u.id} value={u.id}>{u.number}</option>)}
                        </select>
                    </div>
                    {(form.errors.condominium_id || form.errors.unit_id) && <p className="text-xs text-red-600">Selecione condomínio e unidade.</p>}

                    <input value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="Descrição (ex.: Caixa Amazon, carta registrada)" className={input} />
                    {form.errors.description && <p className="text-xs text-red-600">{form.errors.description}</p>}

                    <div className="grid gap-3 sm:grid-cols-2">
                        <input value={form.data.carrier} onChange={(e) => form.setData('carrier', e.target.value)} placeholder="Transportadora/remetente (opcional)" className={input} />
                        <input value={form.data.tracking_code} onChange={(e) => form.setData('tracking_code', e.target.value)} placeholder="Código de rastreio (opcional)" className={input} />
                    </div>

                    <input value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} placeholder="Observações (opcional)" className={input} />

                    <div>
                        <label className="text-sm text-gray-600">Foto (opcional)</label>
                        <input type="file" accept="image/png,image/jpeg,image/webp" onChange={(e) => form.setData('photo', e.target.files?.[0] ?? null)} className="mt-1 block w-full text-sm" />
                        {form.errors.photo && <p className="text-xs text-red-600">{form.errors.photo}</p>}
                    </div>

                    <button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        {form.processing ? 'Salvando…' : 'Salvar e avisar morador'}
                    </button>
                </form>
            )}

            <div className="space-y-2">
                {parcels.length === 0 && <p className="py-8 text-center text-sm text-gray-400">Nenhuma encomenda registrada.</p>}
                {parcels.map((p) => (
                    <div key={p.id} className={`flex items-center gap-3 rounded-xl border bg-white p-3 ${p.status === 'awaiting' ? 'border-amber-200' : 'border-gray-100'}`}>
                        <span className={`flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg ${p.status === 'awaiting' ? 'bg-amber-50 text-amber-600' : 'bg-gray-100 text-gray-400'}`}>
                            <Package className="h-5 w-5" />
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium text-gray-900">{p.description}</p>
                            <p className="truncate text-xs text-gray-500">
                                {p.condominium} — un. {p.unit}{p.carrier ? ` · ${p.carrier}` : ''} · {formatTime(p.received_at)}
                            </p>
                        </div>
                        {p.status === 'awaiting' ? (
                            <button onClick={() => pickup(p.id)} className="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-700">
                                <Check className="h-4 w-4" /> Dar baixa
                            </button>
                        ) : (
                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">{statuses[p.status]}</span>
                        )}
                    </div>
                ))}
            </div>
        </PortariaLayout>
    );
}
