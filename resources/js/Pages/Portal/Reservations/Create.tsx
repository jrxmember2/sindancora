import PortalLayout from '@/Layouts/PortalLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Info } from 'lucide-react';

interface Area {
    id: string; name: string; condominium_id: string;
    requires_approval: boolean; min_advance_days: number;
    opening_time: string | null; closing_time: string | null;
    fee: string | number | null; deposit: string | number | null;
    rules: string | null; capacity: number | null;
}
interface Props {
    areas: Area[];
    selectedArea: string | null;
}

function money(v: string | number | null): string | null {
    if (v === null || v === undefined || Number(v) === 0) return null;
    return Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export default function PortalReservationCreate({ areas, selectedArea }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        common_area_id: selectedArea ?? (areas.length === 1 ? areas[0].id : ''),
        date: new Date().toISOString().slice(0, 10),
        start_time: '',
        end_time: '',
        notes: '',
    });

    const area = areas.find((a) => a.id === data.common_area_id) ?? null;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('portal.reservations.store'));
    };

    const field = 'mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';

    return (
        <PortalLayout>
            <Head title="Nova reserva" />

            <Link href={route('portal.reservations.index')} className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <ArrowLeft className="h-4 w-4" /> Reservas
            </Link>

            <form onSubmit={submit} className="space-y-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <h1 className="text-lg font-bold text-gray-900">Nova reserva</h1>

                <div>
                    <label className="block text-sm font-medium text-gray-700">Área comum *</label>
                    <select value={data.common_area_id} onChange={(e) => setData('common_area_id', e.target.value)} className={field}>
                        <option value="">Selecione…</option>
                        {areas.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                    </select>
                    {errors.common_area_id && <p className="mt-1 text-xs text-red-600">{errors.common_area_id}</p>}
                </div>

                {area && (
                    <div className="rounded-lg bg-blue-50 p-3 text-xs text-blue-800">
                        <p className="flex items-center gap-1.5 font-medium"><Info className="h-3.5 w-3.5" /> Regras da área</p>
                        <ul className="mt-1.5 space-y-0.5">
                            {area.requires_approval ? <li>• Sujeita à aprovação do síndico.</li> : <li>• Confirmação automática (sem aprovação).</li>}
                            {area.min_advance_days > 0 && <li>• Antecedência mínima: {area.min_advance_days} dia(s).</li>}
                            {(area.opening_time || area.closing_time) && <li>• Horário: {area.opening_time?.slice(0, 5) ?? '00:00'} às {area.closing_time?.slice(0, 5) ?? '23:59'}.</li>}
                            {area.capacity ? <li>• Capacidade: {area.capacity} pessoas.</li> : null}
                            {money(area.fee) && <li>• Taxa: {money(area.fee)}.</li>}
                            {money(area.deposit) && <li>• Caução: {money(area.deposit)}.</li>}
                        </ul>
                        {area.rules && <p className="mt-1.5 whitespace-pre-wrap border-t border-blue-100 pt-1.5">{area.rules}</p>}
                    </div>
                )}

                <div>
                    <label className="block text-sm font-medium text-gray-700">Data *</label>
                    <input type="date" value={data.date} min={new Date().toISOString().slice(0, 10)} onChange={(e) => setData('date', e.target.value)} className={field} />
                    {errors.date && <p className="mt-1 text-xs text-red-600">{errors.date}</p>}
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Início *</label>
                        <input type="time" value={data.start_time} onChange={(e) => setData('start_time', e.target.value)} className={field} />
                        {errors.start_time && <p className="mt-1 text-xs text-red-600">{errors.start_time}</p>}
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Fim *</label>
                        <input type="time" value={data.end_time} onChange={(e) => setData('end_time', e.target.value)} className={field} />
                        {errors.end_time && <p className="mt-1 text-xs text-red-600">{errors.end_time}</p>}
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700">Observações</label>
                    <textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} rows={3} className={field} placeholder="Opcional" />
                </div>

                <div className="flex justify-end gap-2 pt-2">
                    <Link href={route('portal.reservations.index')} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</Link>
                    <button type="submit" disabled={processing || !data.common_area_id} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        {processing ? 'Enviando…' : 'Solicitar reserva'}
                    </button>
                </div>
            </form>
        </PortalLayout>
    );
}
