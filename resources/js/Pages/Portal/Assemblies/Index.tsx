import PortalLayout from '@/Layouts/PortalLayout';
import { Head, Link } from '@inertiajs/react';
import { Vote } from 'lucide-react';

interface Row { id: string; title: string; status: string; scheduled_at: string | null; condominium: { name: string } | null }
interface Props { assemblies: Row[] }

const statusStyles: Record<string, string> = {
    open: 'bg-green-100 text-green-700', closed: 'bg-blue-100 text-blue-700',
};
const statusLabels: Record<string, string> = { open: 'Votação aberta', closed: 'Encerrada' };

export default function PortalAssembliesIndex({ assemblies }: Props) {
    return (
        <PortalLayout>
            <Head title="Assembleias" />
            <h1 className="mb-4 flex items-center gap-2 text-xl font-bold text-gray-900"><Vote className="h-5 w-5" /> Assembleias</h1>

            <div className="space-y-2">
                {assemblies.length === 0 && <p className="rounded-xl border border-gray-100 bg-white px-4 py-10 text-center text-sm text-gray-400 shadow-sm">Nenhuma assembleia disponível.</p>}
                {assemblies.map((a) => (
                    <Link key={a.id} href={route('portal.assemblies.show', a.id)} className="flex items-center justify-between gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm hover:bg-gray-50">
                        <div>
                            <p className="font-medium text-gray-900">{a.title}</p>
                            <p className="text-xs text-gray-500">
                                {a.condominium?.name}{a.scheduled_at ? ` · ${new Date(a.scheduled_at).toLocaleString('pt-BR')}` : ''}
                            </p>
                        </div>
                        <span className={`flex-shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold ${statusStyles[a.status] ?? 'bg-gray-100 text-gray-600'}`}>{statusLabels[a.status] ?? a.status}</span>
                    </Link>
                ))}
            </div>
        </PortalLayout>
    );
}
