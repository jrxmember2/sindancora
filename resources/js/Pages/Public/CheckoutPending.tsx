import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Copy, Check, ExternalLink, Loader2 } from 'lucide-react';

interface Payment {
    id?: string;
    status?: string;
    value?: string | number;
    due_date?: string;
    invoice_url?: string | null;
    bank_slip_url?: string | null;
    billing_type?: string;
    pix_payload?: string | null;
    pix_qrcode?: string | null;
}

interface Props {
    signup: { id: string; company_name: string; email: string; billing_type: string; value: string; status: string };
    plan: { display_name: string } | null;
    payment: Payment;
}

const brl = (v: string | number | null | undefined) =>
    v == null ? '—' : `R$ ${parseFloat(String(v)).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;

export default function CheckoutPending({ signup, plan, payment }: Props) {
    const [copied, setCopied] = useState(false);
    const [confirmed, setConfirmed] = useState(false);

    // Polling: pergunta ao backend se o pagamento já compensou e o tenant foi provisionado.
    useEffect(() => {
        const timer = setInterval(async () => {
            try {
                const res = await fetch(`/checkout/${signup.id}/status`, { headers: { Accept: 'application/json' } });
                const data = await res.json();
                if (data.provisioned) {
                    setConfirmed(true);
                    clearInterval(timer);
                    if (data.login_url) setTimeout(() => (window.location.href = data.login_url), 2500);
                }
            } catch {
                /* ignora falha transitória */
            }
        }, 5000);
        return () => clearInterval(timer);
    }, [signup.id]);

    const copyPix = () => {
        if (payment.pix_payload) {
            navigator.clipboard.writeText(payment.pix_payload);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        }
    };

    return (
        <div className="min-h-screen bg-gray-50">
            <Head title="Pagamento — Sindâncora" />

            <div className="mx-auto max-w-lg px-6 py-12">
                <div className="rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
                    {confirmed ? (
                        <div className="text-center">
                            <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-green-100">
                                <Check className="h-7 w-7 text-green-600" />
                            </div>
                            <h1 className="mt-4 text-xl font-bold text-gray-900">Pagamento confirmado!</h1>
                            <p className="mt-2 text-sm text-gray-500">
                                Sua conta foi criada. Enviamos o primeiro acesso para <strong>{signup.email}</strong>. Redirecionando…
                            </p>
                            <Loader2 className="mx-auto mt-4 h-5 w-5 animate-spin text-gray-400" />
                        </div>
                    ) : (
                        <>
                            <h1 className="text-xl font-bold text-gray-900">Conclua o pagamento</h1>
                            <p className="mt-1 text-sm text-gray-500">
                                {plan?.display_name} · {brl(signup.value)}
                            </p>

                            {payment.billing_type === 'PIX' && payment.pix_qrcode ? (
                                <div className="mt-6 text-center">
                                    <img
                                        src={`data:image/png;base64,${payment.pix_qrcode}`}
                                        alt="QR Code PIX"
                                        className="mx-auto h-56 w-56 rounded-lg border border-gray-100"
                                    />
                                    {payment.pix_payload && (
                                        <button
                                            onClick={copyPix}
                                            className="mt-4 inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                        >
                                            {copied ? <Check className="h-4 w-4 text-green-600" /> : <Copy className="h-4 w-4" />}
                                            {copied ? 'Copiado!' : 'Copiar código PIX'}
                                        </button>
                                    )}
                                </div>
                            ) : (
                                <div className="mt-6">
                                    {payment.invoice_url ? (
                                        <a
                                            href={payment.invoice_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 py-3 text-sm font-medium text-white hover:bg-blue-700"
                                        >
                                            <ExternalLink className="h-4 w-4" /> Abrir fatura para pagar
                                        </a>
                                    ) : (
                                        <p className="rounded-lg bg-yellow-50 p-4 text-sm text-yellow-800">
                                            Estamos gerando sua cobrança. Atualize a página em alguns segundos.
                                        </p>
                                    )}
                                </div>
                            )}

                            <div className="mt-6 flex items-center justify-center gap-2 rounded-lg bg-gray-50 py-3 text-sm text-gray-500">
                                <Loader2 className="h-4 w-4 animate-spin" />
                                Aguardando a confirmação do pagamento…
                            </div>

                            <button
                                onClick={() => router.reload()}
                                className="mt-3 w-full text-center text-xs text-gray-400 hover:text-gray-600"
                            >
                                Atualizar dados de pagamento
                            </button>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}
