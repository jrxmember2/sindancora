import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
    LayoutDashboard,
    Building2,
    Users,
    Megaphone,
    AlertCircle,
    CalendarRange,
    FileText,
    Settings,
    LogOut,
    Menu,
    X,
    Bell,
    Shield,
    ClipboardList,
} from 'lucide-react';
import type { PageProps } from '@/types';

const navigation = [
    { name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
    { name: 'Condomínios', href: '/condominios', icon: Building2 },
    { name: 'Usuários', href: '/usuarios', icon: Users },
    { name: 'Comunicados', href: '/comunicados', icon: Megaphone },
    { name: 'Ocorrências', href: '/ocorrencias', icon: AlertCircle },
    { name: 'Reservas', href: '/reservas', icon: CalendarRange },
    { name: 'Documentos', href: '/documentos', icon: FileText },
];

const adminNavigation = [
    { name: 'Perfis', href: '/roles', icon: Shield },
    { name: 'Auditoria', href: '/auditoria', icon: ClipboardList },
];

export default function AppLayout({ children }: { children: React.ReactNode }) {
    const { auth, tenant, flash } = usePage<PageProps>().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);

    const brandName = tenant?.brand_name ?? 'SindÂncora';
    const primaryColor = tenant?.primary_color ?? '#1e40af';

    return (
        <div className="min-h-screen bg-gray-50">
            {/* Sidebar Mobile Overlay */}
            {sidebarOpen && (
                <div
                    className="fixed inset-0 z-40 bg-black/50 lg:hidden"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            {/* Sidebar */}
            <aside
                className={`fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-white shadow-lg transition-transform duration-300 lg:translate-x-0 ${
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                {/* Logo */}
                <div className="flex h-16 items-center justify-between border-b px-4">
                    <div className="flex items-center gap-2">
                        {tenant?.logo_url ? (
                            <img src={tenant.logo_url} alt={brandName} className="h-8 w-auto" />
                        ) : (
                            <div
                                className="flex h-8 w-8 items-center justify-center rounded-lg text-white text-sm font-bold"
                                style={{ backgroundColor: primaryColor }}
                            >
                                {brandName.charAt(0).toUpperCase()}
                            </div>
                        )}
                        <span className="text-lg font-semibold text-gray-900">{brandName}</span>
                    </div>
                    <button
                        onClick={() => setSidebarOpen(false)}
                        className="rounded p-1 text-gray-400 hover:text-gray-600 lg:hidden"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                {/* Navigation */}
                <nav className="flex-1 overflow-y-auto py-4">
                    <ul className="space-y-1 px-2">
                        {navigation.map((item) => {
                            const Icon = item.icon;
                            const isActive = window.location.pathname.startsWith(item.href);
                            return (
                                <li key={item.name}>
                                    <Link
                                        href={item.href}
                                        className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                                            isActive
                                                ? 'bg-blue-50 text-blue-700'
                                                : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                                        }`}
                                    >
                                        <Icon className="h-5 w-5 flex-shrink-0" />
                                        {item.name}
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                    <div className="mt-4 px-2">
                        <p className="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Administração</p>
                        <ul className="space-y-1">
                            {adminNavigation.map((item) => {
                                const Icon = item.icon;
                                const isActive = window.location.pathname.startsWith(item.href);
                                return (
                                    <li key={item.name}>
                                        <Link
                                            href={item.href}
                                            className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                                                isActive
                                                    ? 'bg-blue-50 text-blue-700'
                                                    : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                                            }`}
                                        >
                                            <Icon className="h-5 w-5 flex-shrink-0" />
                                            {item.name}
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                </nav>

                {/* User Menu */}
                <div className="border-t p-4">
                    <div className="flex items-center gap-3">
                        <div className="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-blue-700 text-sm font-semibold">
                            {auth.user?.name?.charAt(0).toUpperCase()}
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium text-gray-900">{auth.user?.name}</p>
                            <p className="truncate text-xs text-gray-500">{auth.user?.email}</p>
                        </div>
                    </div>
                    <div className="mt-3 space-y-1">
                        <Link
                            href="/configuracoes"
                            className="flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs text-gray-600 hover:bg-gray-100"
                        >
                            <Settings className="h-4 w-4" />
                            Configurações
                        </Link>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-xs text-red-600 hover:bg-red-50"
                        >
                            <LogOut className="h-4 w-4" />
                            Sair
                        </Link>
                    </div>
                </div>
            </aside>

            {/* Main Content */}
            <div className="lg:pl-64">
                {/* Topbar */}
                <header className="sticky top-0 z-30 flex h-16 items-center gap-4 border-b bg-white px-4 shadow-sm">
                    <button
                        onClick={() => setSidebarOpen(true)}
                        className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 lg:hidden"
                    >
                        <Menu className="h-5 w-5" />
                    </button>

                    <div className="flex-1" />

                    <button className="relative rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                        <Bell className="h-5 w-5" />
                        <span className="absolute right-1 top-1 h-2 w-2 rounded-full bg-red-500" />
                    </button>
                </header>

                {/* Flash Messages */}
                {(flash?.success || flash?.error) && (
                    <div className="px-6 pt-4">
                        {flash.success && (
                            <div className="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                                {flash.success}
                            </div>
                        )}
                        {flash.error && (
                            <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                                {flash.error}
                            </div>
                        )}
                    </div>
                )}

                {/* Page Content */}
                <main className="p-6">{children}</main>
            </div>
        </div>
    );
}
