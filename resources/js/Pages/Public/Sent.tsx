import { Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import PublicLayout from '@/Layouts/PublicLayout';

interface Props {
    token: string;
    condominium: { name: string };
    protocol: string | null;
}

export default function Sent({ token, condominium, protocol }: Props) {
    return (
        <PublicLayout title="Recebido!" subtitle={condominium.name}>
            <div className="rounded-xl border border-gray-200 bg-white p-8 text-center">
                <CheckCircle2 className="mx-auto h-14 w-14 text-green-500" />
                <h2 className="mt-4 text-lg font-semibold text-gray-900">Envio recebido com sucesso</h2>
                <p className="mt-2 text-sm text-gray-500">
                    A administração do condomínio vai revisar e entrar em contato. Obrigado!
                </p>

                {protocol && (
                    <div className="mx-auto mt-5 max-w-xs rounded-lg border border-dashed border-gray-300 bg-gray-50 py-4">
                        <p className="text-xs text-gray-500">Seu protocolo</p>
                        <p className="font-mono text-2xl font-bold tracking-widest text-gray-900">{protocol}</p>
                        <p className="mt-1 px-4 text-xs text-gray-400">Guarde para acompanhar o andamento.</p>
                    </div>
                )}

                <div className="mt-6 flex flex-col items-center gap-3">
                    <Link
                        href={route('public.intake.status', { token })}
                        className="text-sm font-medium text-blue-600 hover:underline"
                    >
                        Acompanhar pelo protocolo
                    </Link>
                    <Link
                        href={route('public.intake.landing', { token })}
                        className="inline-block rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                    >
                        Voltar ao início
                    </Link>
                </div>
            </div>
        </PublicLayout>
    );
}
