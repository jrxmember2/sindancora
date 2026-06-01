import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Upload, CheckCircle, XCircle, AlertCircle, FileText } from 'lucide-react';
import { useRef, useState } from 'react';

interface PreviewRow {
    line: number; number: string; block_name: string; block_id: string | null;
    floor: number | null; type: string; area_m2: number | null; status: string;
    errors: string[]; valid: boolean;
}
interface PreviewResult { rows: PreviewRow[]; total: number; valid: number; invalid: number }
interface Props { condominium: { id: string; name: string }; blocks: { id: string; name: string }[] }

export default function UnitImport({ condominium }: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<PreviewResult | null>(null);
    const [loading, setLoading] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [fileName, setFileName] = useState('');

    const handleFile = async (file: File) => {
        setFileName(file.name);
        setLoading(true);
        const form = new FormData();
        form.append('file', file);
        form.append('_token', (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '');

        try {
            const res = await fetch(route('condominiums.units.import.preview', condominium.id), {
                method: 'POST',
                body: form,
                headers: { 'X-Inertia': '1' },
            });
            const json = await res.json();
            setPreview(json);
        } catch {
            alert('Erro ao processar o arquivo. Verifique o formato.');
        } finally {
            setLoading(false);
        }
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        const file = e.dataTransfer.files[0];
        if (file) handleFile(file);
    };

    const confirm = () => {
        if (!preview) return;
        const validRows = preview.rows.filter(r => r.valid);
        setSubmitting(true);
        router.post(route('condominiums.units.import.confirm', condominium.id), { rows: validRows as any }, {
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <AppLayout>
            <Head title={`Importar Unidades — ${condominium.name}`} />
            <div className="mx-auto max-w-3xl space-y-6">
                <div>
                    <Link href={route('condominiums.units.index', condominium.id)} className="text-sm text-gray-500 hover:text-gray-700">← {condominium.name} · Unidades</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Importar Unidades via CSV</h1>
                </div>

                {/* Instruções */}
                <div className="rounded-xl bg-blue-50 border border-blue-100 p-4 text-sm text-blue-800">
                    <p className="font-semibold mb-2">Formato do arquivo CSV (separado por ponto e vírgula):</p>
                    <code className="block bg-white rounded p-2 text-xs font-mono text-gray-800">
                        numero;bloco;andar;tipo;area<br />
                        101;Bloco A;1;apartment;65.5<br />
                        102;Bloco A;1;apartment;65.5<br />
                        201;;2;commercial;120
                    </code>
                    <p className="mt-2 text-xs text-blue-600">Tipos aceitos: apartment · house · commercial · garage · storage</p>
                </div>

                {/* Upload */}
                {!preview && (
                    <div
                        className="rounded-xl border-2 border-dashed border-gray-300 bg-white p-12 text-center hover:border-blue-400 transition-colors cursor-pointer"
                        onDragOver={e => e.preventDefault()}
                        onDrop={handleDrop}
                        onClick={() => inputRef.current?.click()}
                    >
                        <input ref={inputRef} type="file" accept=".csv,.txt" className="hidden" onChange={e => e.target.files?.[0] && handleFile(e.target.files[0])} />
                        <Upload className="mx-auto h-12 w-12 text-gray-400" />
                        <p className="mt-4 text-sm font-medium text-gray-700">Arraste o arquivo CSV aqui ou clique para selecionar</p>
                        <p className="mt-1 text-xs text-gray-500">Máximo 2 MB · .csv ou .txt</p>
                        {loading && <p className="mt-4 text-sm text-blue-600 animate-pulse">Processando arquivo…</p>}
                    </div>
                )}

                {/* Preview */}
                {preview && (
                    <div className="space-y-4">
                        {/* Resumo */}
                        <div className="grid grid-cols-3 gap-4">
                            <div className="rounded-xl bg-white border border-gray-100 shadow-sm p-4 text-center">
                                <p className="text-2xl font-bold text-gray-900">{preview.total}</p>
                                <p className="text-xs text-gray-500">Total de linhas</p>
                            </div>
                            <div className="rounded-xl bg-green-50 border border-green-100 shadow-sm p-4 text-center">
                                <p className="text-2xl font-bold text-green-700">{preview.valid}</p>
                                <p className="text-xs text-green-600">Válidas</p>
                            </div>
                            <div className="rounded-xl bg-red-50 border border-red-100 shadow-sm p-4 text-center">
                                <p className="text-2xl font-bold text-red-700">{preview.invalid}</p>
                                <p className="text-xs text-red-600">Com erro</p>
                            </div>
                        </div>

                        {/* Tabela */}
                        <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                            <table className="w-full text-sm">
                                <thead className="bg-gray-50 border-b border-gray-100">
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Linha</th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Número</th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Bloco</th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Andar</th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Tipo</th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Área</th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50">
                                    {preview.rows.map(row => (
                                        <tr key={row.line} className={row.valid ? '' : 'bg-red-50'}>
                                            <td className="px-3 py-2 text-gray-500">{row.line}</td>
                                            <td className="px-3 py-2 font-medium text-gray-900">{row.number || '—'}</td>
                                            <td className="px-3 py-2 text-gray-600">{row.block_name || '—'}</td>
                                            <td className="px-3 py-2 text-gray-600">{row.floor ?? '—'}</td>
                                            <td className="px-3 py-2 text-gray-600">{row.type}</td>
                                            <td className="px-3 py-2 text-gray-600">{row.area_m2 ? `${row.area_m2} m²` : '—'}</td>
                                            <td className="px-3 py-2">
                                                {row.valid ? (
                                                    <CheckCircle className="h-4 w-4 text-green-500" />
                                                ) : (
                                                    <div className="flex items-start gap-1">
                                                        <XCircle className="h-4 w-4 text-red-500 shrink-0 mt-0.5" />
                                                        <span className="text-xs text-red-600">{row.errors.join('; ')}</span>
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {preview.invalid > 0 && (
                            <div className="flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-100 p-3 text-sm text-amber-800">
                                <AlertCircle className="h-4 w-4 shrink-0 mt-0.5" />
                                <span>As {preview.invalid} linha(s) com erro serão ignoradas. Somente as {preview.valid} válidas serão importadas.</span>
                            </div>
                        )}

                        <div className="flex justify-between">
                            <button onClick={() => { setPreview(null); setFileName(''); }} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                <FileText className="h-4 w-4" /> Outro arquivo
                            </button>
                            <button
                                onClick={confirm}
                                disabled={submitting || preview.valid === 0}
                                className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors"
                            >
                                {submitting ? 'Importando…' : `Importar ${preview.valid} unidade(s)`}
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
