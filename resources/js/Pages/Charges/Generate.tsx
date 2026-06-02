import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Layers, AlertTriangle } from 'lucide-react';
import { useState } from 'react';

interface Option { value: string; label: string }
interface Row { unit_id: string; unit_label: string; person_id: string | null; person_name: string | null; amount: number; include: boolean }
interface Props { condominiums: Option[]; types: Record<string, string>; gatewayEnabled: boolean }

const brl = (v: number) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const field = 'mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500';

export default function ChargeGenerate({ condominiums, types, gatewayEnabled }: Props) {
    const [meta, setMeta] = useState({
        condominium_id: '', type: 'condo_fee', description: '', reference_month: '',
        due_date: '', amount: '', fine_rate: '2', interest_rate: '1',
    });
    const [rows, setRows] = useState<Row[] | null>(null);
    const [loading, setLoading] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [issueGateway, setIssueGateway] = useState(false);
    const set = (k: string, v: string) => setMeta((m) => ({ ...m, [k]: v }));

    const loadPreview = async () => {
        if (!meta.condominium_id || !meta.amount) return;
        setLoading(true);
        try {
            const res = await fetch(route('charges.generate.preview'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                },
                body: JSON.stringify({ condominium_id: meta.condominium_id, amount: meta.amount }),
            });
            const data = await res.json();
            setRows(data.rows);
        } finally {
            setLoading(false);
        }
    };

    const updateRow = (i: number, patch: Partial<Row>) => setRows((rs) => rs!.map((r, idx) => idx === i ? { ...r, ...patch } : r));

    const confirm = () => {
        if (!rows) return;
        const included = rows.filter((r) => r.include).map((r) => ({ unit_id: r.unit_id, amount: r.amount, person_id: r.person_id }));
        if (included.length === 0) return;
        setSubmitting(true);
        router.post(route('charges.generate.confirm'), {
            condominium_id: meta.condominium_id,
            type: meta.type,
            description: meta.description,
            reference_month: meta.reference_month || null,
            due_date: meta.due_date,
            fine_rate: meta.fine_rate || 0,
            interest_rate: meta.interest_rate || 0,
            issue_gateway: gatewayEnabled && issueGateway,
            rows: included as any,
        }, { onFinish: () => setSubmitting(false) });
    };

    const includedCount = rows?.filter((r) => r.include).length ?? 0;
    const total = rows?.filter((r) => r.include).reduce((s, r) => s + Number(r.amount), 0) ?? 0;

    return (
        <AppLayout>
            <Head title="Gerar cobranças em lote" />

            <div className="mb-4">
                <Link href={route('charges.index')} className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                    <ArrowLeft className="h-4 w-4" /> Cobranças
                </Link>
                <h1 className="mt-1 text-2xl font-bold text-gray-900">Gerar cobranças em lote</h1>
            </div>

            {/* Passo 1 — parâmetros */}
            <div className="space-y-4 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Condomínio *</label>
                        <select value={meta.condominium_id} onChange={(e) => { set('condominium_id', e.target.value); setRows(null); }} className={field}>
                            <option value="">Selecione…</option>
                            {condominiums.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Descrição *</label>
                        <input value={meta.description} onChange={(e) => set('description', e.target.value)} className={field} placeholder="Ex.: Taxa condominial junho/2026" />
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-5">
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Tipo *</label>
                        <select value={meta.type} onChange={(e) => set('type', e.target.value)} className={field}>
                            {Object.entries(types).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Mês ref.</label>
                        <input type="month" value={meta.reference_month} onChange={(e) => set('reference_month', e.target.value)} className={field} />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Vencimento *</label>
                        <input type="date" value={meta.due_date} onChange={(e) => set('due_date', e.target.value)} className={field} />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Multa (%)</label>
                        <input type="number" step="0.01" min="0" value={meta.fine_rate} onChange={(e) => set('fine_rate', e.target.value)} className={field} />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Juros (%/mês)</label>
                        <input type="number" step="0.01" min="0" value={meta.interest_rate} onChange={(e) => set('interest_rate', e.target.value)} className={field} />
                    </div>
                </div>
                <div className="flex items-end gap-3">
                    <div className="w-40">
                        <label className="block text-sm font-medium text-gray-700">Valor base (R$) *</label>
                        <input type="number" step="0.01" min="0" value={meta.amount} onChange={(e) => set('amount', e.target.value)} className={field} />
                    </div>
                    <button onClick={loadPreview} disabled={!meta.condominium_id || !meta.amount || loading} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        <Layers className="h-4 w-4" /> {loading ? 'Carregando…' : 'Gerar prévia'}
                    </button>
                </div>
            </div>

            {/* Passo 2 — prévia editável */}
            {rows && (
                <div className="mt-6 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    <div className="flex items-center justify-between border-b border-gray-100 p-4">
                        <p className="text-sm font-semibold text-gray-900">{includedCount} unidade(s) · Total {brl(total)}</p>
                    </div>
                    {rows.length === 0 && <p className="px-4 py-8 text-center text-sm text-gray-400">Este condomínio não tem unidades.</p>}
                    {rows.length > 0 && (
                        <table className="w-full text-sm">
                            <thead className="border-b border-gray-100 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th className="px-4 py-2 w-10"></th>
                                    <th className="px-4 py-2">Unidade</th>
                                    <th className="px-4 py-2">Responsável</th>
                                    <th className="px-4 py-2 w-40 text-right">Valor (R$)</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {rows.map((r, i) => (
                                    <tr key={r.unit_id} className={r.include ? '' : 'opacity-40'}>
                                        <td className="px-4 py-2">
                                            <input type="checkbox" checked={r.include} onChange={(e) => updateRow(i, { include: e.target.checked })} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                        </td>
                                        <td className="px-4 py-2 font-medium text-gray-900">{r.unit_label}</td>
                                        <td className="px-4 py-2 text-gray-500">{r.person_name ?? '—'}</td>
                                        <td className="px-4 py-2 text-right">
                                            <input type="number" step="0.01" min="0" value={r.amount} onChange={(e) => updateRow(i, { amount: Number(e.target.value) })} className="w-32 rounded-lg border border-gray-200 px-2 py-1 text-right text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                    {rows.length > 0 && (
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 p-4">
                            <div className="space-y-2">
                                <p className="flex items-center gap-1.5 text-xs text-gray-500"><AlertTriangle className="h-3.5 w-3.5" /> Confira a descrição e o vencimento antes de confirmar.</p>
                                {gatewayEnabled && (
                                    <label className="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" checked={issueGateway} onChange={(e) => setIssueGateway(e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                        Emitir boleto/PIX no Asaas automaticamente (em segundo plano)
                                    </label>
                                )}
                            </div>
                            <button onClick={confirm} disabled={submitting || includedCount === 0 || !meta.description || !meta.due_date} className="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">
                                {submitting ? 'Gerando…' : `Gerar ${includedCount} cobrança(s)`}
                            </button>
                        </div>
                    )}
                </div>
            )}
        </AppLayout>
    );
}
