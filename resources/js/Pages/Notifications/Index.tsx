import AppLayout from '@/Layouts/AppLayout';
import { Head, router } from '@inertiajs/react';
import { Bell, Megaphone, CheckCheck } from 'lucide-react';
import type { AppNotification } from '@/types';

interface Props {
    notifications: { data: AppNotification[]; links: { url: string | null; label: string; active: boolean }[] };
    unread_count: number;
}

const icons: Record<string, typeof Bell> = {
    megaphone: Megaphone,
};

const fmt = (iso: string | null) => (iso ? new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '');

export default function NotificationsIndex({ notifications, unread_count }: Props) {
    const open = (id: string) => router.post(route('notifications.read', id), {}, { preserveScroll: true });
    const markAll = () => router.post(route('notifications.read-all'), {}, { preserveScroll: true });

    return (
        <AppLayout>
            <Head title="Notificações" />
            <div className="mx-auto max-w-2xl space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Bell className="h-6 w-6 text-blue-600" />
                        <h1 className="text-2xl font-bold text-gray-900">Notificações</h1>
                        {unread_count > 0 && (
                            <span className="rounded-full bg-red-500 px-2 py-0.5 text-xs font-semibold text-white">{unread_count}</span>
                        )}
                    </div>
                    {unread_count > 0 && (
                        <button onClick={markAll} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                            <CheckCheck className="h-4 w-4" /> Marcar todas como lidas
                        </button>
                    )}
                </div>

                <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                    {notifications.data.length === 0 && (
                        <p className="px-4 py-10 text-center text-sm text-gray-400">Você não tem notificações.</p>
                    )}
                    {notifications.data.map((n) => {
                        const Icon = icons[n.data.icon ?? ''] ?? Bell;
                        return (
                            <button
                                key={n.id}
                                onClick={() => open(n.id)}
                                className={`flex w-full items-start gap-3 border-b border-gray-50 px-4 py-4 text-left transition-colors hover:bg-gray-50 ${
                                    n.read_at ? '' : 'bg-blue-50/40'
                                }`}
                            >
                                <span className={`flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full ${n.read_at ? 'bg-gray-100 text-gray-400' : 'bg-blue-100 text-blue-600'}`}>
                                    <Icon className="h-4 w-4" />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-medium text-gray-900">{n.data.title}</span>
                                    <span className="block text-sm text-gray-600">{n.data.message}</span>
                                    <span className="mt-0.5 block text-xs text-gray-400">{fmt(n.created_at)}</span>
                                </span>
                                {!n.read_at && <span className="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full bg-blue-500" />}
                            </button>
                        );
                    })}
                </div>

                {notifications.links.length > 3 && (
                    <div className="flex flex-wrap justify-center gap-1">
                        {notifications.links.map((link, i) => (
                            <button
                                key={i}
                                disabled={!link.url}
                                onClick={() => link.url && router.get(link.url)}
                                className={`rounded-lg px-3 py-1.5 text-sm transition-colors ${
                                    link.active ? 'bg-blue-600 text-white' : 'border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-40'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
