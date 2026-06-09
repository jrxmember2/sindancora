import { Link } from '@inertiajs/react';
import { UserPlus, AlertCircle, ChevronRight } from 'lucide-react';
import PublicLayout from '@/Layouts/PublicLayout';

interface LandingProps {
    token: string;
    condominium: { name: string };
    allow: { resident_signup: boolean; occurrence: boolean };
}

export default function Landing({ token, condominium, allow }: LandingProps) {
    const hasAny = allow.resident_signup || allow.occurrence;

    return (
        <PublicLayout title={condominium.name} subtitle="Como podemos ajudar?">
            {!hasAny && (
                <p className="rounded-lg border border-gray-200 bg-white px-4 py-6 text-center text-sm text-gray-500">
                    Este link está temporariamente indisponível. Procure a administração do condomínio.
                </p>
            )}

            <div className="space-y-3">
                {allow.resident_signup && (
                    <Link
                        href={route('public.intake.resident', { token })}
                        className="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 transition hover:border-gray-300 hover:shadow-sm"
                    >
                        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                            <UserPlus className="h-5 w-5" />
                        </span>
                        <span className="flex-1">
                            <span className="block font-semibold text-gray-900">Quero me cadastrar</span>
                            <span className="block text-sm text-gray-500">Sou morador deste condomínio</span>
                        </span>
                        <ChevronRight className="h-5 w-5 text-gray-400" />
                    </Link>
                )}

                {allow.occurrence && (
                    <Link
                        href={route('public.intake.occurrence', { token })}
                        className="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 transition hover:border-gray-300 hover:shadow-sm"
                    >
                        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                            <AlertCircle className="h-5 w-5" />
                        </span>
                        <span className="flex-1">
                            <span className="block font-semibold text-gray-900">Abrir uma ocorrência</span>
                            <span className="block text-sm text-gray-500">Registrar um problema ou solicitação</span>
                        </span>
                        <ChevronRight className="h-5 w-5 text-gray-400" />
                    </Link>
                )}
            </div>

            {hasAny && (
                <div className="mt-6 text-center">
                    <Link href={route('public.intake.status', { token })} className="text-sm font-medium text-blue-600 hover:underline">
                        Já enviei — acompanhar pelo protocolo
                    </Link>
                </div>
            )}

            <p className="mt-4 text-center text-xs text-gray-400">
                Seus dados passam por moderação da administração antes de qualquer acesso.
            </p>
        </PublicLayout>
    );
}
