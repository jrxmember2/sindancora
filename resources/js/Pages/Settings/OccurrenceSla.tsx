import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Timer } from 'lucide-react';

interface Props {
    days: Record<string, number>;
    priorities: Record<string, string>;
    defaults: Record<string, number>;
}

export default function OccurrenceSlaSettings({ days, priorities, defaults }: Props) {
    const form = useForm<{ days: Record<string, number> }>({ days: { ...days } });

    const setDay = (priority: string, value: string) =>
        form.setData('days', { ...form.data.days, [priority]: value === '' ? 0 : Number(value) });

    return (
        <AppLayout>
            <Head title="SLA de chamados" />
            <div className="mx-auto max-w-xl space-y-6">
                <div>
                    <Link href={route('occurrences.dashboard')} className="text-sm text-gray-500 hover:text-gray-700">← Painel de chamados</Link>
                    <div className="mt-1 flex items-center gap-2">
                        <Timer className="h-6 w-6 text-blue-600" />
                        <h1 className="text-2xl font-bold text-gray-900">SLA de chamados</h1>
                    </div>
                    <p className="mt-1 text-sm text-gray-500">Prazo de atendimento (em dias) por prioridade. Aplica-se a novas ocorrências.</p>
                </div>

                <div className="space-y-4 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    {Object.entries(priorities).map(([slug, label]) => (
                        <div key={slug} className="flex items-center justify-between gap-4">
                            <div>
                                <p className="text-sm font-medium text-gray-700">{label}</p>
                                <p className="text-xs text-gray-400">Padrão: {defaults[slug] ?? '—'} dia(s)</p>
                            </div>
                            <div className="flex items-center gap-2">
                                <input
                                    type="number" min={0} max={365}
                                    value={form.data.days[slug] ?? 0}
                                    onChange={e => setDay(slug, e.target.value)}
                                    className="w-24 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                                />
                                <span className="text-sm text-gray-500">dia(s)</span>
                            </div>
                        </div>
                    ))}
                    {form.errors.days && <p className="text-xs text-red-600">Verifique os valores informados.</p>}
                </div>

                <div className="flex justify-end">
                    <button onClick={() => form.put(route('settings.occurrence-sla.update'))} disabled={form.processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50">
                        {form.processing ? 'Salvando…' : 'Salvar SLA'}
                    </button>
                </div>
            </div>
        </AppLayout>
    );
}
