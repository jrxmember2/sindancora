import { Head, router } from '@inertiajs/react';
import { AlertTriangle, ExternalLink, LogOut } from 'lucide-react';

interface Props {
    tenantName: string;
    plan: string | null;
    value: string | null;
    invoiceUrl: string | null;
}

const brl = (v: string | null) =>
    v == null ? null : `R$ ${parseFloat(v).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;

export default function Suspended({ tenantName, plan, value, invoiceUrl }: Props) {
    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 px-6">
            <Head title="Assinatura em atraso" />

            <div className="w-full max-w-md rounded-2xl border border-gray-100 bg-white p-8 text-center shadow-sm">
                <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-100">
                    <AlertTriangle className="h-7 w-7 text-red-600" />
                </div>

                <h1 className="mt-5 text-xl font-bold text-gray-900">Assinatura em atraso</h1>
                <p className="mt-2 text-sm text-gray-500">
                    O acesso de <strong>{tenantName}</strong> está temporariamente bloqueado por falta de pagamento.
                    Regularize a fatura para liberar o sistema automaticamente.
                </p>

                {(plan || value) && (
                    <div className="mt-5 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600">
                        {plan && <p>Plano: <strong>{plan}</strong></p>}
                        {value && <p>Valor: <strong>{brl(value)}</strong></p>}
                    </div>
                )}

                {invoiceUrl ? (
                    <a
                        href={invoiceUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="mt-6 flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 py-3 text-sm font-medium text-white hover:bg-blue-700"
                    >
                        <ExternalLink className="h-4 w-4" /> Pagar fatura agora
                    </a>
                ) : (
                    <p className="mt-6 rounded-lg bg-yellow-50 p-4 text-sm text-yellow-800">
                        Não encontramos uma fatura em aberto. Entre em contato com o suporte para regularizar.
                    </p>
                )}

                <button
                    onClick={() => router.post('/logout')}
                    className="mt-4 inline-flex items-center gap-1.5 text-xs text-gray-400 hover:text-gray-600"
                >
                    <LogOut className="h-3.5 w-3.5" /> Sair
                </button>
            </div>
        </div>
    );
}
