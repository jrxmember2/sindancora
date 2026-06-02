import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Info } from 'lucide-react';

interface Area {
    id: string; name: string; condominium_id: string; requires_approval: boolean; min_advance_days: number;
    opening_time: string | null; closing_time: string | null; fee: string | null; deposit: string | null;
    rules: string | null; capacity: number | null;
}
interface Props {
    areas: Area[];
    selectedArea: string | null;
}

const hhmm = (t: string | null) => (t ? t.slice(0, 5) : null);
const money = (v: string | null) => (v && Number(v) > 0 ? `R$ ${Number(v).toFixed(2).replace('.', ',')}` : null);
const inputClass = 'w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';

export default function ReservationCreate({ areas, selectedArea }: Props) {
    const form = useForm({
        common_area_id: selectedArea ?? (areas.length === 1 ? areas[0].id : ''),
        date: '', start_time: '', end_time: '', notes: '',
    });

    const area = areas.find(a => a.id === form.data.common_area_id);

    return (
        <AppLayout>
            <Head title="Nova Reserva" />
            <div className="space-y-4">
                <div>
                    <Link href={route('reservations.index')} className="text-sm text-gray-500 hover:text-gray-700">← Reservas</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Nova Reserva</h1>
                </div>

                <div className="mx-auto max-w-2xl space-y-6">
                    <div className="space-y-5 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Área comum *</label>
                            <select value={form.data.common_area_id} onChange={e => form.setData('common_area_id', e.target.value)} className={inputClass}>
                                <option value="">Selecione…</option>
                                {areas.map(a => <option key={a.id} value={a.id}>{a.name}</option>)}
                            </select>
                            {form.errors.common_area_id && <p className="mt-1 text-xs text-red-600">{form.errors.common_area_id}</p>}
                        </div>

                        {area && (
                            <div className="rounded-lg bg-blue-50/60 p-3 text-xs text-gray-600">
                                <p className="flex items-center gap-1 font-medium text-gray-700"><Info className="h-3.5 w-3.5" /> Informações da área</p>
                                <ul className="mt-1 space-y-0.5">
                                    <li>Reserva: {area.requires_approval ? 'sujeita a aprovação do síndico' : 'confirmação automática'}</li>
                                    {hhmm(area.opening_time) && hhmm(area.closing_time) && <li>Horário de funcionamento: {hhmm(area.opening_time)} às {hhmm(area.closing_time)}</li>}
                                    {area.min_advance_days > 0 && <li>Antecedência mínima: {area.min_advance_days} dia(s)</li>}
                                    {area.capacity && <li>Capacidade: {area.capacity} pessoas</li>}
                                    {money(area.fee) && <li>Taxa de uso: {money(area.fee)}</li>}
                                    {money(area.deposit) && <li>Caução: {money(area.deposit)}</li>}
                                    {area.rules && <li className="whitespace-pre-wrap">Regras: {area.rules}</li>}
                                </ul>
                            </div>
                        )}

                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Data *</label>
                            <input type="date" value={form.data.date} onChange={e => form.setData('date', e.target.value)} className={inputClass} />
                            {form.errors.date && <p className="mt-1 text-xs text-red-600">{form.errors.date}</p>}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Início *</label>
                                <input type="time" value={form.data.start_time} onChange={e => form.setData('start_time', e.target.value)} className={inputClass} />
                                {form.errors.start_time && <p className="mt-1 text-xs text-red-600">{form.errors.start_time}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Término *</label>
                                <input type="time" value={form.data.end_time} onChange={e => form.setData('end_time', e.target.value)} className={inputClass} />
                                {form.errors.end_time && <p className="mt-1 text-xs text-red-600">{form.errors.end_time}</p>}
                            </div>
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Observações</label>
                            <textarea value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} rows={3} className={`${inputClass} resize-none`} maxLength={1000} />
                        </div>
                    </div>

                    <div className="flex items-center justify-between">
                        <Link href={route('reservations.index')} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">Cancelar</Link>
                        <button onClick={() => form.post(route('reservations.store'))} disabled={form.processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50">
                            {form.processing ? 'Enviando…' : 'Solicitar reserva'}
                        </button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
