import { useEffect, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Send, ArrowLeft, Users, RefreshCw } from 'lucide-react';

interface Option { value: string; label: string }
interface Connection { value: string; label: string; connected: boolean }
interface Props {
    connections: Connection[];
    condominiums: Option[];
    mediaMaxMb: number;
}

type TargetType = 'all' | 'blocks' | 'units';

export default function CampaignsCreate({ connections, condominiums, mediaMaxMb }: Props) {
    const form = useForm<{
        name: string; connection_id: string; condominium_id: string; body: string;
        target_type: TargetType; block_ids: string[]; unit_ids: string[];
        throttle_seconds: number; sign: boolean; scheduled_at: string; file: File | null;
    }>({
        name: '', connection_id: connections[0]?.value ?? '', condominium_id: '',
        body: '', target_type: 'all', block_ids: [], unit_ids: [],
        throttle_seconds: 10, sign: false, scheduled_at: '', file: null,
    });

    const [targets, setTargets] = useState<{ blocks: Option[]; units: Option[] }>({ blocks: [], units: [] });
    const [count, setCount] = useState<number | null>(null);
    const [counting, setCounting] = useState(false);

    useEffect(() => {
        setCount(null);
        if (!form.data.condominium_id) { setTargets({ blocks: [], units: [] }); return; }
        window.axios.get(route('campaigns.targets', form.data.condominium_id))
            .then((r) => setTargets(r.data))
            .catch(() => setTargets({ blocks: [], units: [] }));
        form.setData((d) => ({ ...d, block_ids: [], unit_ids: [] }));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [form.data.condominium_id]);

    const preview = () => {
        if (!form.data.condominium_id) return;
        setCounting(true);
        window.axios.post(route('campaigns.preview'), {
            condominium_id: form.data.condominium_id,
            target_type: form.data.target_type,
            block_ids: form.data.block_ids,
            unit_ids: form.data.unit_ids,
        }).then((r) => setCount(r.data.count)).finally(() => setCounting(false));
    };

    const toggle = (key: 'block_ids' | 'unit_ids', id: string) => {
        const set = new Set(form.data[key]);
        set.has(id) ? set.delete(id) : set.add(id);
        form.setData(key, Array.from(set));
        setCount(null);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('campaigns.store'), { forceFormData: true });
    };

    const selectedConnection = connections.find((c) => c.value === form.data.connection_id);

    return (
        <AppLayout>
            <Head title="Nova campanha" />

            <div className="mx-auto max-w-2xl space-y-6">
                <Link href={route('campaigns.index')} className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                    <ArrowLeft className="h-4 w-4" /> Disparos
                </Link>
                <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900">
                    <Send className="h-6 w-6 text-blue-600" /> Nova campanha
                </h1>

                <form onSubmit={submit} className="space-y-5 rounded-xl border border-gray-200 bg-white p-5">
                    <Field label="Nome da campanha" error={form.errors.name}>
                        <input type="text" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" />
                    </Field>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Conexão" error={form.errors.connection_id}>
                            <select value={form.data.connection_id} onChange={(e) => form.setData('connection_id', e.target.value)} className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                {connections.map((c) => <option key={c.value} value={c.value}>{c.label}{c.connected ? '' : ' (desconectada)'}</option>)}
                            </select>
                            {selectedConnection && !selectedConnection.connected && <p className="mt-1 text-xs text-amber-600">Conexão desconectada — conecte antes de enviar.</p>}
                        </Field>
                        <Field label="Condomínio" error={form.errors.condominium_id}>
                            <select value={form.data.condominium_id} onChange={(e) => form.setData('condominium_id', e.target.value)} className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Selecione…</option>
                                {condominiums.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                            </select>
                        </Field>
                    </div>

                    {/* Segmentação */}
                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Público-alvo</label>
                        <div className="flex gap-2 text-sm">
                            {([['all', 'Todos do condomínio'], ['blocks', 'Por bloco'], ['units', 'Por unidade']] as const).map(([v, l]) => (
                                <button key={v} type="button" disabled={!form.data.condominium_id} onClick={() => { form.setData('target_type', v); setCount(null); }} className={`rounded-lg border px-3 py-1.5 font-medium disabled:opacity-50 ${form.data.target_type === v ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-gray-300 text-gray-600 hover:bg-gray-50'}`}>
                                    {l}
                                </button>
                            ))}
                        </div>

                        {form.data.target_type === 'blocks' && (
                            <CheckGrid options={targets.blocks} selected={form.data.block_ids} onToggle={(id) => toggle('block_ids', id)} empty="Sem blocos cadastrados." />
                        )}
                        {form.data.target_type === 'units' && (
                            <CheckGrid options={targets.units} selected={form.data.unit_ids} onToggle={(id) => toggle('unit_ids', id)} empty="Sem unidades cadastradas." />
                        )}
                    </div>

                    {/* Prévia de destinatários */}
                    <div className="flex items-center gap-3 rounded-lg bg-gray-50 px-3 py-2">
                        <button type="button" onClick={preview} disabled={!form.data.condominium_id || counting} className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                            {counting ? <RefreshCw className="h-4 w-4 animate-spin" /> : <Users className="h-4 w-4" />} Calcular destinatários
                        </button>
                        {count !== null && <span className="text-sm text-gray-700"><b>{count}</b> destinatário(s) elegível(is) (sem descadastrados).</span>}
                    </div>

                    <Field label="Mensagem" error={form.errors.body}>
                        <textarea value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} rows={5} placeholder="Texto do disparo…" className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" />
                    </Field>

                    <Field label={`Anexo (opcional, até ${mediaMaxMb} MB)`} error={form.errors.file}>
                        <input type="file" onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} className="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-blue-700" />
                    </Field>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Intervalo entre envios (segundos)" error={form.errors.throttle_seconds} hint="Maior = mais seguro contra bloqueio.">
                            <input type="number" min={1} max={300} value={form.data.throttle_seconds} onChange={(e) => form.setData('throttle_seconds', Number(e.target.value))} className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" />
                        </Field>
                        <Field label="Agendar (opcional)" error={form.errors.scheduled_at} hint="Em branco = inicia manualmente.">
                            <input type="datetime-local" value={form.data.scheduled_at} onChange={(e) => form.setData('scheduled_at', e.target.value)} className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" />
                        </Field>
                    </div>

                    <label className="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" checked={form.data.sign} onChange={(e) => form.setData('sign', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                        Assinar com meu nome (adiciona seu nome no início de cada mensagem)
                    </label>

                    <div className="flex justify-end gap-2 border-t border-gray-100 pt-4">
                        <Link href={route('campaigns.index')} className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</Link>
                        <button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Criar campanha</button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

function Field({ label, error, hint, children }: { label: string; error?: string; hint?: string; children: React.ReactNode }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
            {children}
            {hint && !error && <p className="mt-1 text-xs text-gray-400">{hint}</p>}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

function CheckGrid({ options, selected, onToggle, empty }: { options: Option[]; selected: string[]; onToggle: (id: string) => void; empty: string }) {
    if (options.length === 0) return <p className="mt-2 text-xs text-gray-400">{empty}</p>;
    return (
        <div className="mt-2 grid max-h-44 gap-1.5 overflow-y-auto rounded-lg border border-gray-100 bg-gray-50 p-2 sm:grid-cols-2">
            {options.map((o) => (
                <label key={o.value} className="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" checked={selected.includes(o.value)} onChange={() => onToggle(o.value)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                    {o.label}
                </label>
            ))}
        </div>
    );
}
