import { useForm, Link } from '@inertiajs/react';
import PortalLayout from '@/Layouts/PortalLayout';
import { ArrowLeft } from 'lucide-react';

interface UnitOption { id: string; label: string }

export default function VisitorsCreate({ units }: { units: UnitOption[] }) {
    const form = useForm({
        unit_id: units.length === 1 ? units[0].id : '',
        visitor_name: '',
        visitor_document: '',
        visitor_phone: '',
        type: 'single',
        valid_from: '',
        valid_until: '',
        notes: '',
    });

    const field = 'w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500';

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('portal.visitors.store'));
    };

    return (
        <PortalLayout title="Autorizar visitante">
            <Link href={route('portal.visitors.index')} className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <ArrowLeft className="h-4 w-4" /> Voltar
            </Link>

            <form onSubmit={submit} className="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
                <div>
                    <label className="mb-1 block text-sm font-medium text-gray-700">Unidade *</label>
                    <select value={form.data.unit_id} onChange={(e) => form.setData('unit_id', e.target.value)} className={field}>
                        <option value="">Selecione…</option>
                        {units.map((u) => <option key={u.id} value={u.id}>{u.label}</option>)}
                    </select>
                    {form.errors.unit_id && <p className="mt-1 text-xs text-red-600">{form.errors.unit_id}</p>}
                </div>

                <div>
                    <label className="mb-1 block text-sm font-medium text-gray-700">Nome do visitante *</label>
                    <input type="text" value={form.data.visitor_name} onChange={(e) => form.setData('visitor_name', e.target.value)} className={field} />
                    {form.errors.visitor_name && <p className="mt-1 text-xs text-red-600">{form.errors.visitor_name}</p>}
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Documento</label>
                        <input type="text" value={form.data.visitor_document} onChange={(e) => form.setData('visitor_document', e.target.value)} className={field} />
                    </div>
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Telefone</label>
                        <input type="text" value={form.data.visitor_phone} onChange={(e) => form.setData('visitor_phone', e.target.value)} className={field} />
                    </div>
                </div>

                <div>
                    <label className="mb-1 block text-sm font-medium text-gray-700">Tipo</label>
                    <select value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} className={field}>
                        <option value="single">Visita única</option>
                        <option value="recurring">Recorrente (ex.: diarista, familiar)</option>
                    </select>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
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

                <div>
                    <label className="mb-1 block text-sm font-medium text-gray-700">Observações</label>
                    <textarea value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} rows={2} className={field} />
                </div>

                <button type="submit" disabled={form.processing} className="w-full rounded-lg bg-blue-600 py-2.5 font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    Gerar autorização e QR Code
                </button>
            </form>
        </PortalLayout>
    );
}
