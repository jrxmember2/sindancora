import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Pencil, Trash2, Plus, X, Play, Square, FileText, Download, Vote } from 'lucide-react';

interface OptionResult { id: string; label: string; votes: number; percent: number }
interface ItemResult { id: string; title: string; description: string | null; total_votes: number; options: OptionResult[]; winner: string | null }
interface Item { id: string; title: string; description: string | null; options: { id: string; label: string }[] }
interface Assembly {
    id: string; title: string; description: string | null; status: string; scheduled_at: string | null;
    minutes: string | null; has_minutes: boolean; condominium: { name: string } | null; items: Item[];
}
interface Props {
    assembly: Assembly;
    results: { total_units: number; present_units: number; items: ItemResult[] };
    statuses: Record<string, string>;
}

const statusStyles: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-600', open: 'bg-green-100 text-green-700', closed: 'bg-blue-100 text-blue-700',
};

export default function AssemblyShow({ assembly, results, statuses }: Props) {
    const isDraft = assembly.status === 'draft';
    const isClosed = assembly.status === 'closed';

    const { data, setData, post, processing, reset } = useForm<{ title: string; description: string; options: string[] }>({
        title: '', description: '', options: ['', ''],
    });

    const setOption = (i: number, v: string) => setData('options', data.options.map((o, idx) => (idx === i ? v : o)));
    const addOption = () => setData('options', [...data.options, '']);
    const removeOption = (i: number) => setData('options', data.options.filter((_, idx) => idx !== i));

    const addItem = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('assemblies.items.store', assembly.id), { preserveScroll: true, onSuccess: () => reset() });
    };

    const action = (name: string, confirmMsg?: string) => {
        if (confirmMsg && !confirm(confirmMsg)) return;
        router.post(route(name, assembly.id), {}, { preserveScroll: true });
    };
    const removeItem = (itemId: string) => confirm('Remover este item?') && router.delete(route('assemblies.items.destroy', [assembly.id, itemId]), { preserveScroll: true });
    const removeAssembly = () => confirm('Remover esta assembleia?') && router.delete(route('assemblies.destroy', assembly.id));

    const field = 'mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';

    return (
        <AppLayout>
            <Head title={assembly.title} />

            <div className="mb-4">
                <Link href={route('assemblies.index')} className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                    <ArrowLeft className="h-4 w-4" /> Assembleias
                </Link>
            </div>

            <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <div className="flex items-start justify-between gap-3 border-b border-gray-100 p-5">
                    <div>
                        <h1 className="flex items-center gap-2 text-lg font-bold text-gray-900"><Vote className="h-5 w-5" /> {assembly.title}</h1>
                        <p className="text-xs text-gray-500">
                            {assembly.condominium?.name}
                            {assembly.scheduled_at ? ` · ${new Date(assembly.scheduled_at).toLocaleString('pt-BR')}` : ''}
                            {` · Presença: ${results.present_units}/${results.total_units} unidades`}
                        </p>
                        {assembly.description && <p className="mt-2 text-sm text-gray-700">{assembly.description}</p>}
                    </div>
                    <span className={`flex-shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold ${statusStyles[assembly.status]}`}>{statuses[assembly.status]}</span>
                </div>

                {/* Ações de status */}
                <div className="flex flex-wrap gap-2 border-b border-gray-100 p-4">
                    {isDraft && (
                        <>
                            <button onClick={() => action('assemblies.open', 'Abrir a votação? A pauta não poderá mais ser alterada.')} className="inline-flex items-center gap-2 rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700">
                                <Play className="h-4 w-4" /> Abrir votação
                            </button>
                            <Link href={route('assemblies.edit', assembly.id)} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <Pencil className="h-4 w-4" /> Editar
                            </Link>
                            <button onClick={removeAssembly} className="inline-flex items-center gap-2 rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                                <Trash2 className="h-4 w-4" /> Remover
                            </button>
                        </>
                    )}
                    {assembly.status === 'open' && (
                        <button onClick={() => action('assemblies.close', 'Encerrar a votação?')} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">
                            <Square className="h-4 w-4" /> Encerrar votação
                        </button>
                    )}
                    {isClosed && (
                        <>
                            <button onClick={() => action('assemblies.minutes')} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                <FileText className="h-4 w-4" /> {assembly.has_minutes ? 'Regerar ata' : 'Gerar ata'}
                            </button>
                            {assembly.has_minutes && (
                                <a href={route('assemblies.minutes.pdf', assembly.id)} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    <Download className="h-4 w-4" /> Baixar ata (PDF)
                                </a>
                            )}
                        </>
                    )}
                </div>

                {/* Pauta (rascunho: gestão; demais: resultados) */}
                <div className="space-y-4 p-5">
                    {isDraft ? (
                        <>
                            {assembly.items.length > 0 && (
                                <div className="space-y-2">
                                    {assembly.items.map((it, i) => (
                                        <div key={it.id} className="flex items-start justify-between gap-3 rounded-lg border border-gray-100 p-3">
                                            <div>
                                                <p className="text-sm font-medium text-gray-900">{i + 1}. {it.title}</p>
                                                {it.description && <p className="text-xs text-gray-500">{it.description}</p>}
                                                <p className="mt-1 text-xs text-gray-400">Opções: {it.options.map((o) => o.label).join(' · ')}</p>
                                            </div>
                                            <button onClick={() => removeItem(it.id)} className="text-gray-300 hover:text-red-600"><Trash2 className="h-4 w-4" /></button>
                                        </div>
                                    ))}
                                </div>
                            )}

                            <form onSubmit={addItem} className="space-y-3 rounded-lg border border-dashed border-gray-200 p-4">
                                <p className="text-sm font-semibold text-gray-900">Adicionar item à pauta</p>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Título *</label>
                                    <input value={data.title} onChange={(e) => setData('title', e.target.value)} className={field} placeholder="Ex.: Aprovação das contas" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Descrição</label>
                                    <input value={data.description} onChange={(e) => setData('description', e.target.value)} className={field} />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Opções de voto *</label>
                                    <div className="mt-1 space-y-2">
                                        {data.options.map((o, i) => (
                                            <div key={i} className="flex gap-2">
                                                <input value={o} onChange={(e) => setOption(i, e.target.value)} className="flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder={`Opção ${i + 1}`} />
                                                {data.options.length > 2 && (
                                                    <button type="button" onClick={() => removeOption(i)} className="rounded-lg border border-gray-200 p-2 text-gray-400 hover:text-red-600"><X className="h-4 w-4" /></button>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                    <button type="button" onClick={addOption} className="mt-2 inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-700"><Plus className="h-3.5 w-3.5" /> Adicionar opção</button>
                                </div>
                                <div className="flex justify-end">
                                    <button type="submit" disabled={processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Adicionar item</button>
                                </div>
                            </form>
                        </>
                    ) : (
                        <div className="space-y-4">
                            {results.items.map((it, i) => (
                                <div key={it.id} className="rounded-lg border border-gray-100 p-4">
                                    <div className="flex items-center justify-between">
                                        <p className="text-sm font-medium text-gray-900">{i + 1}. {it.title}</p>
                                        <span className="text-xs text-gray-400">{it.total_votes} voto(s)</span>
                                    </div>
                                    {it.description && <p className="mb-2 text-xs text-gray-500">{it.description}</p>}
                                    <div className="mt-2 space-y-1.5">
                                        {it.options.map((o) => (
                                            <div key={o.id}>
                                                <div className="flex justify-between text-xs text-gray-600">
                                                    <span>{o.label}{it.winner === o.label && it.total_votes > 0 ? ' ✓' : ''}</span>
                                                    <span>{o.votes} ({o.percent}%)</span>
                                                </div>
                                                <div className="mt-0.5 h-2 rounded-full bg-gray-100">
                                                    <div className="h-2 rounded-full bg-blue-500" style={{ width: `${o.percent}%` }} />
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {isClosed && assembly.minutes && (
                        <div className="rounded-lg border border-gray-100 bg-gray-50 p-4">
                            <p className="mb-2 text-sm font-semibold text-gray-900">Ata</p>
                            <p className="whitespace-pre-wrap text-sm text-gray-700">{assembly.minutes}</p>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
