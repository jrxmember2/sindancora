import PortalLayout from '@/Layouts/PortalLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface Option { value: string; label: string }
interface Props {
    units: Option[];
    categories: Record<string, string>;
    priorities: Record<string, string>;
}

export default function PortalOccurrenceCreate({ units, categories, priorities }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        unit_id: units.length === 1 ? units[0].value : '',
        title: '',
        description: '',
        category: 'maintenance',
        priority: 'normal',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('portal.occurrences.store'));
    };

    const field = 'mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';

    return (
        <PortalLayout>
            <Head title="Nova ocorrência" />

            <Link href={route('portal.occurrences.index')} className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <ArrowLeft className="h-4 w-4" /> Ocorrências
            </Link>

            <form onSubmit={submit} className="space-y-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <h1 className="text-lg font-bold text-gray-900">Abrir nova ocorrência</h1>

                <div>
                    <label className="block text-sm font-medium text-gray-700">Unidade *</label>
                    <select value={data.unit_id} onChange={(e) => setData('unit_id', e.target.value)} className={field}>
                        <option value="">Selecione…</option>
                        {units.map((u) => <option key={u.value} value={u.value}>{u.label}</option>)}
                    </select>
                    {errors.unit_id && <p className="mt-1 text-xs text-red-600">{errors.unit_id}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700">Título *</label>
                    <input value={data.title} onChange={(e) => setData('title', e.target.value)} className={field} placeholder="Ex.: Lâmpada queimada no corredor" />
                    {errors.title && <p className="mt-1 text-xs text-red-600">{errors.title}</p>}
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Categoria *</label>
                        <select value={data.category} onChange={(e) => setData('category', e.target.value)} className={field}>
                            {Object.entries(categories).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Prioridade *</label>
                        <select value={data.priority} onChange={(e) => setData('priority', e.target.value)} className={field}>
                            {Object.entries(priorities).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                        </select>
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700">Descrição *</label>
                    <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={5} className={field} placeholder="Descreva o que está acontecendo…" />
                    {errors.description && <p className="mt-1 text-xs text-red-600">{errors.description}</p>}
                </div>

                <div className="flex justify-end gap-2 pt-2">
                    <Link href={route('portal.occurrences.index')} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</Link>
                    <button type="submit" disabled={processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        {processing ? 'Enviando…' : 'Abrir ocorrência'}
                    </button>
                </div>
            </form>
        </PortalLayout>
    );
}
