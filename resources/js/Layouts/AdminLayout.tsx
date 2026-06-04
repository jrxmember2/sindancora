import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
    LayoutDashboard, Building2, LogOut, Menu, X, Shield, Package, MessageCircle,
} from 'lucide-react';
import type { PageProps } from '@/types';

const navigation = [
    { name: 'Dashboard', href: '/admin', icon: LayoutDashboard },
    { name: 'Tenants', href: '/admin/tenants', icon: Building2 },
    { name: 'Planos', href: '/admin/planos', icon: Package },
    { name: 'WhatsApp', href: '/admin/whatsapp', icon: MessageCircle },
];

export default function AdminLayout({ children }: { children: React.ReactNode }) {
    const { auth, flash } = usePage<PageProps>().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);

    return (
        <div className="min-h-screen bg-gray-50">
            {sidebarOpen && (
                <div className="fixed inset-0 z-40 bg-black/50 lg:hidden" onClick={() => setSidebarOpen(false)} />
            )}

            <aside className={`fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-gray-900 transition-transform duration-300 lg:translate-x-0 ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}`}>
                <div className="flex h-16 items-center justify-between border-b border-gray-700 px-4">
                    <div className="flex items-center gap-2">
                        <Shield className="h-6 w-6 text-blue-400" />
                        <span className="text-lg font-semibold text-white">Super Admin</span>
                    </div>
                    <button onClick={() => setSidebarOpen(false)} className="rounded p-1 text-gray-400 hover:text-white lg:hidden">
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <nav className="flex-1 overflow-y-auto py-4">
                    <ul className="space-y-1 px-2">
                        {navigation.map((item) => {
                            const Icon = item.icon;
                            const isActive = window.location.pathname === item.href || (item.href !== '/admin' && window.location.pathname.startsWith(item.href));
                            return (
                                <li key={item.name}>
                                    <Link
                                        href={item.href}
                                        className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${isActive ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white'}`}
                                    >
                                        <Icon className="h-5 w-5 flex-shrink-0" />
                                        {item.name}
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </nav>

                <div className="border-t border-gray-700 p-4">
                    <div className="flex items-center gap-3 mb-3">
                        <div className="flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 text-white text-sm font-semibold">
                            {auth.user?.name?.charAt(0).toUpperCase()}
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium text-white">{auth.user?.name}</p>
                            <p className="truncate text-xs text-gray-400">{auth.user?.email}</p>
                        </div>
                    </div>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-xs text-red-400 hover:bg-gray-800"
                    >
                        <LogOut className="h-4 w-4" />
                        Sair
                    </Link>
                </div>
            </aside>

            <div className="lg:pl-64">
                <header className="sticky top-0 z-30 flex h-16 items-center gap-4 border-b bg-gray-900 px-4">
                    <button onClick={() => setSidebarOpen(true)} className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-800 hover:text-white lg:hidden">
                        <Menu className="h-5 w-5" />
                    </button>
                    <div className="flex items-center gap-2">
                        <Shield className="h-4 w-4 text-blue-400" />
                        <span className="text-sm font-medium text-gray-300">Painel de Administração</span>
                    </div>
                </header>

                {(flash?.success || flash?.error) && (
                    <div className="px-6 pt-4">
                        {flash.success && (
                            <div className="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{flash.success}</div>
                        )}
                        {flash.error && (
                            <div className="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">{flash.error}</div>
                        )}
                    </div>
                )}

                <main className="p-6">{children}</main>
            </div>
        </div>
    );
}
