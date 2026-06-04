import { useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import PortariaLayout from '@/Layouts/PortariaLayout';
import { QrCode, CheckCircle2, XCircle, LogIn } from 'lucide-react';

interface ValidationResult {
    found: boolean;
    id?: string;
    visitor_name?: string;
    visitor_document?: string | null;
    type_label?: string;
    status_label?: string;
    valid?: boolean;
    condominium?: string | null;
    unit?: string | null;
    valid_from?: string | null;
    valid_until?: string | null;
}

export default function Validate({ result, token }: { result: ValidationResult | null; token: string }) {
    const form = useForm({ token: token ?? '' });
    const [submitting, setSubmitting] = useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('portaria.validate.check'), { preserveScroll: true });
    };

    const registerEntry = () => {
        if (!result?.id) return;
        router.post(route('portaria.checkin.authorized'), { authorization_id: result.id }, {
            onStart: () => setSubmitting(true),
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <PortariaLayout title="Validar QR / código">
            <form onSubmit={submit} className="mb-6 rounded-xl border border-gray-200 bg-white p-5">
                <label className="mb-1 block text-sm font-medium text-gray-700">Código do visitante</label>
                <p className="mb-3 text-sm text-gray-500">Leia o QR Code do visitante ou digite o código informado pelo morador.</p>
                <div className="flex gap-2">
                    <div className="relative flex-1">
                        <QrCode className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            autoFocus
                            value={form.data.token}
                            onChange={(e) => form.setData('token', e.target.value.toUpperCase())}
                            placeholder="EX: A1B2C3D4"
                            className="w-full rounded-lg border-gray-300 pl-10 font-mono text-lg uppercase tracking-wider focus:border-blue-500 focus:ring-blue-500"
                        />
                    </div>
                    <button type="submit" disabled={form.processing || !form.data.token} className="rounded-lg bg-blue-600 px-5 font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        Validar
                    </button>
                </div>
                {form.errors.token && <p className="mt-1 text-xs text-red-600">{form.errors.token}</p>}
            </form>

            {/* Resultado */}
            {result && !result.found && (
                <div className="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-5">
                    <XCircle className="h-8 w-8 flex-shrink-0 text-red-500" />
                    <div>
                        <p className="font-semibold text-red-800">Código não encontrado</p>
                        <p className="text-sm text-red-600">Verifique o código com o morador.</p>
                    </div>
                </div>
            )}

            {result && result.found && (
                <div className={`rounded-xl border p-5 ${result.valid ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50'}`}>
                    <div className="mb-4 flex items-center gap-3">
                        {result.valid ? (
                            <CheckCircle2 className="h-8 w-8 flex-shrink-0 text-green-600" />
                        ) : (
                            <XCircle className="h-8 w-8 flex-shrink-0 text-amber-600" />
                        )}
                        <div>
                            <p className={`font-semibold ${result.valid ? 'text-green-800' : 'text-amber-800'}`}>
                                {result.valid ? 'Autorização válida' : `Não liberada — ${result.status_label}`}
                            </p>
                            <p className="text-sm text-gray-600">{result.type_label}</p>
                        </div>
                    </div>

                    <dl className="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt className="text-gray-500">Visitante</dt>
                            <dd className="font-medium text-gray-900">{result.visitor_name}</dd>
                        </div>
                        {result.visitor_document && (
                            <div>
                                <dt className="text-gray-500">Documento</dt>
                                <dd className="font-medium text-gray-900">{result.visitor_document}</dd>
                            </div>
                        )}
                        <div>
                            <dt className="text-gray-500">Condomínio</dt>
                            <dd className="font-medium text-gray-900">{result.condominium}</dd>
                        </div>
                        <div>
                            <dt className="text-gray-500">Unidade</dt>
                            <dd className="font-medium text-gray-900">{result.unit ?? '—'}</dd>
                        </div>
                        {(result.valid_from || result.valid_until) && (
                            <div className="col-span-2">
                                <dt className="text-gray-500">Validade</dt>
                                <dd className="font-medium text-gray-900">
                                    {result.valid_from ?? '…'} até {result.valid_until ?? 'sem limite'}
                                </dd>
                            </div>
                        )}
                    </dl>

                    {result.valid && (
                        <button
                            onClick={registerEntry}
                            disabled={submitting}
                            className="mt-5 flex w-full items-center justify-center gap-2 rounded-lg bg-green-600 py-3 font-semibold text-white hover:bg-green-700 disabled:opacity-50"
                        >
                            <LogIn className="h-5 w-5" /> Registrar entrada
                        </button>
                    )}
                </div>
            )}
        </PortariaLayout>
    );
}
