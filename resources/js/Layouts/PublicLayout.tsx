import { Head, usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';
import type { PropsWithChildren } from 'react';

interface PublicLayoutProps {
    title: string;
    subtitle?: string;
}

/**
 * Layout mínimo e branded para as páginas públicas (links/QR por condomínio). Não exige login;
 * usa a identidade do tenant (logo, nome e cor) compartilhada pelo Inertia.
 */
export default function PublicLayout({ title, subtitle, children }: PropsWithChildren<PublicLayoutProps>) {
    const { tenant, flash } = usePage<PageProps>().props;
    const brandName = tenant?.brand_name ?? 'SindÂncora';
    const primaryColor = tenant?.primary_color ?? '#1e40af';

    return (
        <div className="min-h-screen bg-gray-50">
            <Head title={title} />

            <header className="border-b border-gray-100 bg-white" style={{ borderTopColor: primaryColor, borderTopWidth: 3 }}>
                <div className="mx-auto flex max-w-lg items-center gap-3 px-4 py-4">
                    {tenant?.logo_url ? (
                        <img src={tenant.logo_url} alt={brandName} className="h-9 max-w-[160px] object-contain" />
                    ) : (
                        <span className="text-lg font-bold text-gray-900">{brandName}</span>
                    )}
                </div>
            </header>

            <main className="mx-auto max-w-lg px-4 py-8">
                <div className="mb-6">
                    <h1 className="text-xl font-bold text-gray-900">{title}</h1>
                    {subtitle && <p className="mt-1 text-sm text-gray-500">{subtitle}</p>}
                </div>

                {flash?.success && (
                    <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {flash.error}
                    </div>
                )}

                {children}
            </main>

            <footer className="mx-auto max-w-lg px-4 py-6 text-center text-xs text-gray-400">
                Atendimento de {brandName}
            </footer>
        </div>
    );
}
