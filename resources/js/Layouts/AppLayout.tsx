import { useEffect, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import {
    LayoutDashboard,
    Building2,
    Users,
    UserRound,
    Megaphone,
    AlertCircle,
    CalendarDays,
    CalendarRange,
    FileText,
    Settings,
    LogOut,
    Menu,
    X,
    Bell,
    Shield,
    ClipboardList,
    Wallet,
    Receipt,
    BarChart3,
    KeyRound,
    Webhook,
    MessageCircle,
    Sparkles,
    Vote,
    DoorOpen,
    MessagesSquare,
    Headset,
    Bot,
    MessageSquareText,
    Send,
    Mail,
    Tags,
    Truck,
    BriefcaseBusiness,
    Wrench,
    Timer,
    ClipboardCheck,
    Hammer,
    QrCode,
    ShipWheel,
    ChevronDown,
    ChevronRight,
    HardDrive,
    AlertTriangle,
    Package,
    ListChecks,
    PackageSearch,
    Newspaper,
} from 'lucide-react';
import type { PageProps } from '@/types';
import FreeSpaceModal from '@/Components/FreeSpaceModal';

const navigation = [
    { name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
    { name: 'Cronograma', href: '/cronograma', icon: CalendarDays, permission: 'schedule:read', module: 'schedule' },
    { name: 'Condomínios', href: '/condominios', icon: Building2, permission: 'condominiums:read', module: 'condominiums' },
    { name: 'Pessoas', href: '/pessoas', icon: UserRound, permission: 'persons:read', module: 'persons' },
    { name: 'Usuários', href: '/usuarios', icon: Users, permission: 'users:read' },
    { name: 'Comunicados', href: '/comunicados', icon: Megaphone, permission: 'announcements:read', module: 'announcements' },
    { name: 'Ocorrências', href: '/ocorrencias', icon: AlertCircle, permission: 'occurrences:read', module: 'occurrences' },
    { name: 'Reservas', href: '/reservas', icon: CalendarRange, permission: 'reservations:read', module: 'reservations' },
    { name: 'Documentos', href: '/documentos', icon: FileText, permission: 'documents:read', module: 'documents' },
    { name: 'Cobranças', href: '/cobrancas', icon: Wallet, permission: 'charges:read', module: 'financial' },
    { name: 'Contas a pagar', href: '/despesas', icon: Receipt, permission: 'expenses:read', module: 'financial' },
    { name: 'Fornecedores', href: '/fornecedores', icon: Truck, permission: 'suppliers:read', module: 'suppliers' },
    { name: 'Funcionarios', href: '/funcionarios', icon: BriefcaseBusiness, permission: 'employees:read', module: 'employees' },
    { name: 'Manutenção', href: '/manutencoes', icon: Wrench, permission: 'maintenance:read', module: 'maintenance' },
    { name: 'Orçamentos', href: '/orcamentos', icon: ClipboardCheck, permission: 'quotations:read', module: 'quotations' },
    { name: 'Obras', href: '/obras', icon: Hammer, permission: 'works:read', module: 'works' },
    { name: 'Relatórios', href: '/relatorios', icon: BarChart3, permission: 'reports:read', module: 'reports' },
    { name: 'Assembleias', href: '/assembleias', icon: Vote, permission: 'assemblies:read', module: 'assemblies' },
    { name: 'Enquetes', href: '/enquetes', icon: ListChecks, permission: 'polls:read', module: 'polls' },
    { name: 'Achados & Perdidos', href: '/achados-perdidos', icon: PackageSearch, permission: 'lost_found:read', module: 'lost_found' },
    { name: 'Multas/Advertencias', href: '/multas-advertencias', icon: AlertTriangle, permission: 'disciplinary:read', module: 'disciplinary' },
    { name: 'Mural', href: '/mural', icon: Newspaper, permission: 'community_board:read', module: 'community_board' },
    { name: 'Portaria', href: '/visitantes', icon: DoorOpen, permission: 'gatehouse:read', module: 'gatehouse' },
    { name: 'Encomendas', href: '/encomendas', icon: Package, permission: 'gatehouse:read', module: 'gatehouse' },
    { name: 'Links públicos', href: '/links-publicos', icon: QrCode, permission: 'public_links:read', module: 'public_links' },
    { name: 'Atendimento', href: '/inbox', icon: MessagesSquare, permission: 'inbox:use', module: 'whatsapp' },
    { name: 'Setores', href: '/setores', icon: Headset, permission: 'sectors:manage', module: 'whatsapp' },
    { name: 'Disparos', href: '/disparos', icon: Send, permission: 'campaigns:manage', module: 'whatsapp' },
    { name: 'LemeIA', href: '/assistente', icon: ShipWheel, permission: 'ai:use', module: 'ai_assistant' },
];

const adminNavigation = [
    { name: 'Perfis', href: '/roles', icon: Shield, permission: 'users:manage' },
    { name: 'Dados do tenant', href: '/configuracoes/tenant', icon: Building2, permission: 'settings:update' },
    { name: 'Armazenamento', href: '/configuracoes/armazenamento', icon: HardDrive, permission: 'settings:update' },
    { name: 'Pagamentos', href: '/configuracoes/pagamentos', icon: Settings, permission: 'settings:payments', module: 'financial' },
    { name: 'E-mail (SMTP)', href: '/configuracoes/email', icon: Mail, permission: 'settings:email' },
    { name: 'Categorias', href: '/configuracoes/categorias', icon: Tags, permission: 'categories:manage' },
    { name: 'SLA de chamados', href: '/configuracoes/ocorrencias', icon: Timer, permission: 'occurrences:update', module: 'occurrences' },
    { name: 'API', href: '/configuracoes/api', icon: KeyRound, permission: 'api_keys:manage', module: 'api' },
    { name: 'Webhooks', href: '/configuracoes/webhooks', icon: Webhook, permission: 'webhooks:manage', module: 'webhooks' },
    { name: 'WhatsApp', href: '/configuracoes/whatsapp/conexoes', icon: MessageCircle, permission: 'settings:whatsapp', module: 'whatsapp' },
    { name: 'Chatbot', href: '/configuracoes/chatbot', icon: Bot, permission: 'sectors:manage', module: 'whatsapp' },
    { name: 'Respostas rápidas', href: '/respostas-rapidas', icon: MessageSquareText, permission: 'sectors:manage', module: 'whatsapp' },
    { name: 'Relatório de atendimento', href: '/inbox/relatorios', icon: BarChart3, permission: 'sectors:manage', module: 'whatsapp' },
    { name: 'Auditoria', href: '/auditoria', icon: ClipboardList, permission: 'audit:read' },
];

function timeAgo(iso: string | null): string {
    if (!iso) return '';
    const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
    if (diff < 60) return 'agora';
    if (diff < 3600) return `há ${Math.floor(diff / 60)} min`;
    if (diff < 86400) return `há ${Math.floor(diff / 3600)} h`;
    return `há ${Math.floor(diff / 86400)} d`;
}

export default function AppLayout({ children }: { children: React.ReactNode }) {
    const { auth, tenant, flash, notifications } = usePage<PageProps>().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [notifOpen, setNotifOpen] = useState(false);
    const [adminOpen, setAdminOpen] = useState(false);
    const [freeSpaceOpen, setFreeSpaceOpen] = useState(false);

    const unread = notifications?.unread_count ?? 0;
    const recent = notifications?.recent ?? [];

    // Tempo real (Reverb): atualiza o sino ao receber notificação transmitida.
    const userId = auth.user?.id;
    useEffect(() => {
        if (!window.Echo || !userId) return;
        const channel = `App.Models.User.${userId}`;
        window.Echo.private(channel).notification(() => router.reload({ only: ['notifications'] }));
        return () => { window.Echo?.leave(channel); };
    }, [userId]);

    const openNotification = (id: string) => router.post(route('notifications.read', id), {}, { preserveScroll: true });
    const markAllRead = () => router.post(route('notifications.read-all'), {}, { preserveScroll: true, onSuccess: () => setNotifOpen(false) });

    const perms = auth.user?.permissions ?? [];
    const can = (permission?: string) => !permission || perms.includes('*') || perms.includes(permission);
    const canUseModule = (module?: string) => !module
        || auth.user?.is_super_admin
        || tenant?.plan?.modules?.includes(module);
    const visibleNav = navigation.filter((item) => can(item.permission) && canUseModule(item.module));
    const visibleAdminNav = adminNavigation.filter((item) => can(item.permission) && canUseModule(item.module));

    const brandName = tenant?.brand_name ?? 'SindÂncora';

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
                <div className="relative flex h-16 items-center justify-center border-b px-4">
                    <div className="flex items-center gap-2">
                        <img src="/brand/logo.svg" alt="SindÂncora" className="h-10 w-auto" />
                        <span className="text-xl font-bold tracking-tight text-gray-900">SindÂncora</span>
                    </div>
                    <button
                        onClick={() => setSidebarOpen(false)}
                        className="absolute right-3 rounded p-1 text-gray-400 hover:text-gray-600 lg:hidden"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                {/* Navigation */}
                <nav className="flex-1 overflow-y-auto py-4">
                    <ul className="space-y-1 px-2">
                        {visibleNav.map((item) => {
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
                    {visibleAdminNav.length > 0 && (
                    <div className="mt-4 px-2">
                        <button
                            onClick={() => setAdminOpen((o) => !o)}
                            className="flex w-full items-center justify-between rounded-lg px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"
                            aria-expanded={adminOpen}
                        >
                            <span>Administração</span>
                            {adminOpen ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                        </button>
                        {adminOpen && (
                        <ul className="mt-1 space-y-1">
                            {visibleAdminNav.map((item) => {
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
                        )}
                    </div>
                    )}
                </nav>

                {/* User Menu */}
                <div className="border-t p-4">
                    <Link
                        href="/perfil"
                        className="flex items-center gap-3 rounded-lg border border-gray-200 p-2 transition-colors hover:border-blue-300 hover:bg-gray-50"
                        title="Ver meu perfil"
                    >
                        {auth.user?.avatar_url ? (
                            <img src={auth.user.avatar_url} alt={auth.user.name} className="h-9 w-9 rounded-full object-cover" />
                        ) : (
                            <div className="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-blue-700 text-sm font-semibold">
                                {auth.user?.name?.charAt(0).toUpperCase()}
                            </div>
                        )}
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium text-gray-900">{auth.user?.name}</p>
                            <p className="truncate text-xs text-gray-500">{auth.user?.email}</p>
                        </div>
                    </Link>
                    <div className="mt-3 space-y-1">
                        {can('settings:payments') && (
                            <Link
                                href="/configuracoes/pagamentos"
                                className="flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs text-gray-600 hover:bg-gray-100"
                            >
                                <Settings className="h-4 w-4" />
                                Configurações
                            </Link>
                        )}
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
            <div className="flex min-h-screen flex-col lg:pl-64">
                {/* Topbar */}
                <header className="sticky top-0 z-30 flex h-16 items-center gap-4 border-b bg-white px-4 shadow-sm">
                    <button
                        onClick={() => setSidebarOpen(true)}
                        className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 lg:hidden"
                    >
                        <Menu className="h-5 w-5" />
                    </button>

                    {tenant && (
                        <div className="flex min-w-0 items-center">
                            <span className="min-w-0 truncate text-sm font-semibold text-gray-700" title={brandName}>
                                {brandName}
                            </span>
                        </div>
                    )}

                    <div className="flex-1" />

                    {/* Sino de notificações */}
                    <div className="relative">
                        <button
                            onClick={() => setNotifOpen((o) => !o)}
                            className="relative rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        >
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
                                <div className="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-lg">
                                    <div className="flex items-center justify-between border-b border-gray-100 px-4 py-2.5">
                                        <span className="text-sm font-semibold text-gray-900">Notificações</span>
                                        {unread > 0 && (
                                            <button onClick={markAllRead} className="text-xs text-blue-600 hover:text-blue-700">
                                                Marcar todas como lidas
                                            </button>
                                        )}
                                    </div>
                                    <div className="max-h-80 overflow-y-auto">
                                        {recent.length === 0 && (
                                            <p className="px-4 py-6 text-center text-sm text-gray-400">Nenhuma notificação.</p>
                                        )}
                                        {recent.map((n) => (
                                            <button
                                                key={n.id}
                                                onClick={() => openNotification(n.id)}
                                                className={`flex w-full items-start gap-2 border-b border-gray-50 px-4 py-3 text-left transition-colors hover:bg-gray-50 ${
                                                    n.read_at ? '' : 'bg-blue-50/40'
                                                }`}
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
                                    <Link
                                        href="/notificacoes"
                                        onClick={() => setNotifOpen(false)}
                                        className="block border-t border-gray-100 px-4 py-2.5 text-center text-sm font-medium text-blue-600 hover:bg-gray-50"
                                    >
                                        Ver todas
                                    </Link>
                                </div>
                            </>
                        )}
                    </div>
                </header>

                {/* Alerta de armazenamento (≥ 85%) — só para quem pode agir (settings:update) */}
                {tenant?.storage?.is_near_limit && can('settings:update') && (
                    <div className="px-6 pt-4">
                        <div className="flex flex-wrap items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            <AlertTriangle className="h-5 w-5 flex-shrink-0 text-red-600" />
                            <span className="min-w-0 flex-1">
                                Seu armazenamento está em <strong>{Math.round(tenant.storage.percentage_used)}%</strong>.
                                Libere espaço removendo a mídia mais antiga do WhatsApp.
                            </span>
                            <button
                                onClick={() => setFreeSpaceOpen(true)}
                                className="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-red-700"
                            >
                                Liberar espaço
                            </button>
                            <Link href="/configuracoes/armazenamento" className="text-xs font-medium text-red-700 underline hover:text-red-900">
                                Gerenciar
                            </Link>
                        </div>
                    </div>
                )}

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

                <FreeSpaceModal open={freeSpaceOpen} onClose={() => setFreeSpaceOpen(false)} />

                {/* Page Content */}
                <main className="flex-1 p-6">{children}</main>

                <footer className="border-t border-gray-100 bg-white px-6 py-4 text-center text-xs text-gray-400">
                    <a
                        href="https://serratech.tec.br"
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1.5 text-gray-400 transition hover:text-gray-600"
                    >
                        powered by
                        <img src="/brand/serratech-full.svg" alt="Serratech" className="h-4 w-auto" />
                    </a>
                </footer>
            </div>
        </div>
    );
}
