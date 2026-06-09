import { Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import PublicLayout from '@/Layouts/PublicLayout';

interface Props {
    token: string;
    condominium: { name: string };
}

export default function Sent({ token, condominium }: Props) {
    return (
        <PublicLayout title="Recebido!" subtitle={condominium.name}>
            <div className="rounded-xl border border-gray-200 bg-white p-8 text-center">
                <CheckCircle2 className="mx-auto h-14 w-14 text-green-500" />
                <h2 className="mt-4 text-lg font-semibold text-gray-900">Envio recebido com sucesso</h2>
                <p className="mt-2 text-sm text-gray-500">
                    A administração do condomínio vai revisar e entrar em contato. Obrigado!
                </p>
                <Link
                    href={route('public.intake.landing', { token })}
                    className="mt-6 inline-block rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                >
                    Voltar ao início
                </Link>
            </div>
        </PublicLayout>
    );
}
