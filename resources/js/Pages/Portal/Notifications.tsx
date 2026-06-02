import PortalLayout from '@/Layouts/PortalLayout';
import { Head, router } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';

interface AppNotification {
    id: string;
    data: { title: string; message: string; url?: string };
    read_at: string | null;
    created_at: string | null;
}
interface Props {
    notifications: { data: AppNotification[] };
    unread_count: number;
}

export default function PortalNotifications({ notifications, unread_count }: Props) {
    const open = (id: string) => router.post(route('notifications.read', id));
    const markAll = () => router.post(route('notifications.read-all'), {}, { preserveScroll: true });

    return (
        <PortalLayout title="Notificações">
            <Head title="Notificações" />

            {unread_count > 0 && (
                <div className="mb-3 flex justify-end">
                    <button onClick={markAll} className="inline-flex items-center gap-1.5 text-sm text-blue-600 hover:text-blue-700">
                        <CheckCheck className="h-4 w-4" /> Marcar todas como lidas
                    </button>
                </div>
            )}

            <div className="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                {notifications.data.length === 0 && (
                    <div className="px-4 py-10 text-center">
                        <Bell className="mx-auto h-8 w-8 text-gray-300" />
                        <p className="mt-2 text-sm text-gray-400">Nenhuma notificação.</p>
                    </div>
                )}
                {notifications.data.map((n) => (
                    <button
                        key={n.id}
                        onClick={() => open(n.id)}
                        className={`flex w-full items-start gap-3 px-4 py-3.5 text-left transition-colors hover:bg-gray-50 ${n.read_at ? '' : 'bg-blue-50/40'}`}
                    >
                        {!n.read_at && <span className="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full bg-blue-500" />}
                        <div className={`min-w-0 flex-1 ${n.read_at ? 'pl-5' : ''}`}>
                            <p className="text-sm font-medium text-gray-900">{n.data.title}</p>
                            <p className="text-sm text-gray-600">{n.data.message}</p>
                            <p className="mt-0.5 text-[11px] text-gray-400">{n.created_at ? new Date(n.created_at).toLocaleString('pt-BR') : ''}</p>
                        </div>
                    </button>
                ))}
            </div>
        </PortalLayout>
    );
}
