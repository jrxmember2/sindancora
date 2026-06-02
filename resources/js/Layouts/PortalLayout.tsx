import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import {
    Home,
    Megaphone,
    AlertCircle,
    CalendarRange,
    FileText,
    Building2,
    Bell,
    LogOut,
    UserRound,
    ChevronDown,
    Wallet,
} from 'lucide-react';
import type { PageProps } from '@/types';

const nav = [
    { name: 'Início', href: '/portal', icon: Home, match: (p: string) => p === '/portal' },
    { name: 'Comunicados', href: '/portal/comunicados', icon: Megaphone, match: (p: string) => p.startsWith('/portal/comunicados') },
    { name: 'Cobranças', href: '/portal/cobrancas', icon: Wallet, match: (p: string) => p.startsWith('/portal/cobrancas') },
    { name: 'Ocorrências', href: '/portal/ocorrencias', icon: AlertCircle, match: (p: string) => p.startsWith('/portal/ocorrencias') },
    { name: 'Reservas', href: '/portal/reservas', icon: CalendarRange, match: (p: string) => p.startsWith('/portal/reservas') },
    { name: 'Documentos', href: '/portal/documentos', icon: FileText, match: (p: string) => p.startsWith('/portal/documentos') },
    { name: 'Minha unidade', href: '/portal/minha-unidade', icon: Building2, match: (p: string) => p.startsWith('/portal/minha-unidade') },
];

// Itens da barra inferior (mobile) — os 5 mais usados.
const bottomNav = nav.slice(0, 5);

function timeAgo(iso: string | null): string {
    if (!iso) return '';
    const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
    if (diff < 60) return 'agora';
    if (diff < 3600) return `há ${Math.floor(diff / 60)} min`;
    if (diff < 86400) return `há ${Math.floor(diff / 3600)} h`;
    return `há ${Math.floor(diff / 86400)} d`;
}

export default function PortalLayout({ children, title }: { children: React.ReactNode; title?: string }) {
    const { auth, tenant, flash, notifications } = usePage<PageProps>().props;
    const [notifOpen, setNotifOpen] = useState(false);
    const [menuOpen, setMenuOpen] = useState(false);

    const path = typeof window !== 'undefined' ? window.location.pathname : '';
    const unread = notifications?.unread_count ?? 0;
    const recent = notifications?.recent ?? [];
    const openNotification = (id: string) => router.post(route('notifications.read', id), {}, { preserveScroll: true });
    const markAllRead = () => router.post(route('notifications.read-all'), {}, { preserveScroll: true, onSuccess: () => setNotifOpen(false) });

    const brandName = tenant?.brand_name ?? 'SindÂncora';
    const primaryColor = tenant?.primary_color ?? '#1e40af';

    return (
        <div className="min-h-screen bg-gray-50">
            {/* Topbar */}
            <header className="sticky top-0 z-30 flex h-14 items-center gap-3 border-b bg-white px-4 shadow-sm">
                <Link href="/portal" className="flex items-center gap-2">
                    {tenant?.logo_url ? (
                        <img src={tenant.logo_url} alt={brandName} className="h-8 w-auto" />
                    ) : (
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg text-sm font-bold text-white" style={{ backgroundColor: primaryColor }}>
                            {brandName.charAt(0).toUpperCase()}
                        </div>
                    )}
                    <span className="text-base font-semibold text-gray-900">{brandName}</span>
                </Link>

                <div className="flex-1" />

                {/* Sino */}
                <div className="relative">
                    <button onClick={() => setNotifOpen((o) => !o)} className="relative rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                        <Bell className="h-5 w-5" />
                        {unread > 0 && (
                            <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white">
                                {unread > 9 ? '9+' : unread}
                            </span>
                        )}
                    </button>
                    {notifOpen && (
                        <>
                            <div className="fixed inset-0 z-40" onClick={() => setNotifOpen(false)} />
                            <div className="absolute right-0 z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-gray-100 bg-white shadow-lg">
                                <div className="flex items-center justify-between border-b border-gray-100 px-4 py-2.5">
                                    <span className="text-sm font-semibold text-gray-900">Notificações</span>
                                    {unread > 0 && (
                                        <button onClick={markAllRead} className="text-xs text-blue-600 hover:text-blue-700">Marcar todas como lidas</button>
                                    )}
                                </div>
                                <div className="max-h-80 overflow-y-auto">
                                    {recent.length === 0 && <p className="px-4 py-6 text-center text-sm text-gray-400">Nenhuma notificação.</p>}
                                    {recent.map((n) => (
                                        <button
                                            key={n.id}
                                            onClick={() => openNotification(n.id)}
                                            className={`flex w-full items-start gap-2 border-b border-gray-50 px-4 py-3 text-left transition-colors hover:bg-gray-50 ${n.read_at ? '' : 'bg-blue-50/40'}`}
                                        >
                                            {!n.read_at && <span className="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full bg-blue-500" />}
                                            <span className={`min-w-0 flex-1 ${n.read_at ? 'pl-4' : ''}`}>
                                                <span className="block text-sm font-medium text-gray-900">{n.data.title}</span>
                                                <span className="block truncate text-xs text-gray-500">{n.data.message}</span>
                                                <span className="mt-0.5 block text-[11px] text-gray-400">{timeAgo(n.created_at)}</span>
                                            </span>
                                        </button>
                                    ))}
                                </div>
                                <Link href="/notificacoes" onClick={() => setNotifOpen(false)} className="block border-t border-gray-100 px-4 py-2.5 text-center text-sm font-medium text-blue-600 hover:bg-gray-50">
                                    Ver todas
                                </Link>
                            </div>
                        </>
                    )}
                </div>

                {/* Menu do usuário */}
                <div className="relative">
                    <button onClick={() => setMenuOpen((o) => !o)} className="flex items-center gap-1.5 rounded-lg p-1 hover:bg-gray-100">
                        <span className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-700">
                            {auth.user?.name?.charAt(0).toUpperCase()}
                        </span>
                        <ChevronDown className="hidden h-4 w-4 text-gray-400 sm:block" />
                    </button>
                    {menuOpen && (
                        <>
                            <div className="fixed inset-0 z-40" onClick={() => setMenuOpen(false)} />
                            <div className="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-lg">
                                <div className="border-b border-gray-100 px-4 py-3">
                                    <p className="truncate text-sm font-medium text-gray-900">{auth.user?.name}</p>
                                    <p className="truncate text-xs text-gray-500">{auth.user?.email}</p>
                                </div>
                                <Link href="/portal/perfil" onClick={() => setMenuOpen(false)} className="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">
                                    <UserRound className="h-4 w-4" /> Meu perfil
                                </Link>
                                <Link href="/portal/minha-unidade" onClick={() => setMenuOpen(false)} className="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">
                                    <Building2 className="h-4 w-4" /> Minha unidade
                                </Link>
                                <Link href="/portal/documentos" onClick={() => setMenuOpen(false)} className="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">
                                    <FileText className="h-4 w-4" /> Documentos
                                </Link>
                                <Link href="/logout" method="post" as="button" className="flex w-full items-center gap-2 border-t border-gray-100 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50">
                                    <LogOut className="h-4 w-4" /> Sair
                                </Link>
                            </div>
                        </>
                    )}
                </div>
            </header>

            <div className="mx-auto flex w-full max-w-5xl gap-6 px-4 py-5 lg:px-6">
                {/* Sidebar (desktop) */}
                <aside className="hidden w-52 flex-shrink-0 lg:block">
                    <nav className="sticky top-20 space-y-1">
                        {nav.map((item) => {
                            const Icon = item.icon;
                            const active = item.match(path);
                            return (
                                <Link
                                    key={item.name}
                                    href={item.href}
                                    className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${active ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'}`}
                                >
                                    <Icon className="h-5 w-5 flex-shrink-0" />
                                    {item.name}
                                </Link>
                            );
                        })}
                    </nav>
                </aside>

                {/* Conteúdo */}
                <main className="min-w-0 flex-1 pb-20 lg:pb-0">
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

            {/* Tab bar (mobile) */}
            <nav className="fixed bottom-0 left-0 right-0 z-30 flex border-t bg-white lg:hidden">
                {bottomNav.map((item) => {
                    const Icon = item.icon;
                    const active = item.match(path);
                    return (
                        <Link key={item.name} href={item.href} className={`flex flex-1 flex-col items-center gap-0.5 py-2 text-[11px] font-medium ${active ? 'text-blue-600' : 'text-gray-500'}`}>
                            <Icon className="h-5 w-5" />
                            {item.name}
                        </Link>
                    );
                })}
            </nav>
        </div>
    );
}
