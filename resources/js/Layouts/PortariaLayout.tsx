import { Link, usePage } from '@inertiajs/react';
import { DoorOpen, QrCode, History, LogOut } from 'lucide-react';
import type { PageProps } from '@/types';

const nav = [
    { name: 'Portaria', href: '/portaria', icon: DoorOpen, match: (p: string) => p === '/portaria' },
    { name: 'Validar QR', href: '/portaria/validar', icon: QrCode, match: (p: string) => p.startsWith('/portaria/validar') },
    { name: 'Histórico', href: '/portaria/visitas', icon: History, match: (p: string) => p.startsWith('/portaria/visitas') },
];

export default function PortariaLayout({ children, title }: { children: React.ReactNode; title?: string }) {
    const { auth, tenant, flash } = usePage<PageProps>().props;
    const path = typeof window !== 'undefined' ? window.location.pathname : '';

    const brandName = tenant?.brand_name ?? 'SindÂncora';
    const primaryColor = tenant?.primary_color ?? '#1e40af';

    return (
        <div className="min-h-screen bg-gray-100">
            {/* Topbar */}
            <header className="sticky top-0 z-30 flex h-14 items-center gap-3 border-b bg-white px-4 shadow-sm">
                <Link href="/portaria" className="flex items-center gap-2">
                    {tenant?.logo_url ? (
                        <img src={tenant.logo_url} alt={brandName} className="h-8 w-auto" />
                    ) : (
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg text-sm font-bold text-white" style={{ backgroundColor: primaryColor }}>
                            {brandName.charAt(0).toUpperCase()}
                        </div>
                    )}
                    <span className="text-base font-semibold text-gray-900">{brandName}</span>
                    <span className="ml-1 rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">Portaria</span>
                </Link>

                <div className="flex-1" />

                <span className="hidden text-sm text-gray-600 sm:block">{auth.user?.name}</span>
                <Link href="/logout" method="post" as="button" className="flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm text-red-600 hover:bg-red-50">
                    <LogOut className="h-4 w-4" /> <span className="hidden sm:inline">Sair</span>
                </Link>
            </header>

            {/* Nav horizontal (topo, fácil no tablet) */}
            <nav className="flex border-b bg-white">
                {nav.map((item) => {
                    const Icon = item.icon;
                    const active = item.match(path);
                    return (
                        <Link
                            key={item.name}
                            href={item.href}
                            className={`flex flex-1 items-center justify-center gap-2 py-3 text-sm font-medium transition-colors sm:flex-none sm:px-6 ${active ? 'border-b-2 border-blue-600 text-blue-700' : 'text-gray-500 hover:bg-gray-50'}`}
                        >
                            <Icon className="h-5 w-5" />
                            {item.name}
                        </Link>
                    );
                })}
            </nav>

            <main className="mx-auto w-full max-w-4xl px-4 py-5">
                {title && <h1 className="mb-4 text-xl font-bold text-gray-900">{title}</h1>}

                {(flash?.success || flash?.error) && (
                    <div className="mb-4">
                        {flash.success && <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{flash.success}</div>}
                        {flash.error && <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{flash.error}</div>}
                    </div>
                )}

                {children}
            </main>
        </div>
    );
}
