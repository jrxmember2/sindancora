import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Search, Clock, CheckCircle2, XCircle } from 'lucide-react';
import { FormEventHandler } from 'react';
import PublicLayout from '@/Layouts/PublicLayout';

interface Result {
    found: boolean;
    type_label?: string;
    status?: string;
    status_label?: string;
    created_at?: string;
}

interface Props {
    token: string;
    condominium: { name: string };
    result: Result | null;
    submitted?: { protocol: string };
}

const statusStyles: Record<string, { bg: string; icon: typeof Clock; text: string }> = {
    pending: { bg: 'bg-amber-50 text-amber-700', icon: Clock, text: 'Em análise pela administração.' },
    approved: { bg: 'bg-green-50 text-green-700', icon: CheckCircle2, text: 'Aprovado! A administração dará o andamento.' },
    rejected: { bg: 'bg-red-50 text-red-700', icon: XCircle, text: 'Não aprovado. Procure a administração para detalhes.' },
};

export default function Status({ token, condominium, result, submitted }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        protocol: submitted?.protocol ?? '',
        phone: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('public.intake.status.check', { token }), { preserveScroll: true });
    };

    const field = 'mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500';
    const status = result?.found && result.status ? statusStyles[result.status] : null;
    const StatusIcon = status?.icon;

    return (
        <PublicLayout title="Acompanhar envio" subtitle={condominium.name}>
            <Link href={route('public.intake.landing', { token })} className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <ArrowLeft className="h-4 w-4" /> Voltar
            </Link>

            <form onSubmit={submit} className="space-y-4 rounded-xl border border-gray-200 bg-white p-5">
                <div>
                    <label className="block text-sm font-medium text-gray-700">Protocolo *</label>
                    <input type="text" value={data.protocol} onChange={(e) => setData('protocol', e.target.value.toUpperCase())} className={`${field} font-mono tracking-widest`} placeholder="XXXXXXXX" />
                    {errors.protocol && <p className="mt-1 text-xs text-red-600">{errors.protocol}</p>}
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700">Telefone usado no envio *</label>
                    <input type="tel" value={data.phone} onChange={(e) => setData('phone', e.target.value)} className={field} placeholder="(00) 00000-0000" />
                    {errors.phone && <p className="mt-1 text-xs text-red-600">{errors.phone}</p>}
                </div>
                <button type="submit" disabled={processing} className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                    <Search className="h-4 w-4" /> Consultar
                </button>
            </form>

            {result && (
                <div className="mt-4">
                    {result.found && status && StatusIcon ? (
                        <div className={`rounded-xl border border-gray-200 p-5 ${status.bg}`}>
                            <div className="flex items-center gap-2">
                                <StatusIcon className="h-5 w-5" />
                                <span className="font-semibold">{result.status_label}</span>
                            </div>
                            <p className="mt-2 text-sm">{result.type_label} · {status.text}</p>
                        </div>
                    ) : (
                        <p className="rounded-xl border border-gray-200 bg-white px-4 py-5 text-center text-sm text-gray-500">
                            Não encontramos um envio com esse protocolo e telefone. Confira os dados.
                        </p>
                    )}
                </div>
            )}
        </PublicLayout>
    );
}
