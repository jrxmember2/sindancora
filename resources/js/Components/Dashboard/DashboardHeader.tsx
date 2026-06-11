import { Clock, RefreshCw, SlidersHorizontal } from 'lucide-react';
import { timeAgo } from '@/lib/format';
import type { DashboardHeaderData } from './types';

export default function DashboardHeader({
    header,
    onRefresh,
    onCustomize,
    customizing,
}: {
    header: DashboardHeaderData;
    onRefresh: () => void;
    onCustomize: () => void;
    customizing: boolean;
}) {
    return (
        <div className="overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-800 to-blue-900 p-6 text-white shadow-sm">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                    <p className="text-sm text-blue-200">SindÂncora · Gestão Condominial</p>
                    <h1 className="mt-0.5 truncate text-2xl font-bold tracking-tight">
                        {header.greeting}, {header.user_name.split(' ')[0]} 👋
                    </h1>
                    <p className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-blue-100/80">
                        <span>{header.condominium_count} condomínio(s) no painel</span>
                        <span className="hidden sm:inline">·</span>
                        <span className="inline-flex items-center gap-1">
                            <Clock className="h-3.5 w-3.5" /> Atualizado {timeAgo(header.updated_at)}
                        </span>
                    </p>
                </div>
                <div className="flex flex-shrink-0 items-center gap-2">
                    <button
                        onClick={onRefresh}
                        className="inline-flex items-center gap-2 rounded-xl bg-white/10 px-3 py-2 text-sm font-medium text-white backdrop-blur transition hover:bg-white/20"
                    >
                        <RefreshCw className="h-4 w-4" /> Atualizar
                    </button>
                    <button
                        onClick={onCustomize}
                        className={`inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium transition ${
                            customizing ? 'bg-white text-slate-900' : 'bg-white/10 text-white backdrop-blur hover:bg-white/20'
                        }`}
                    >
                        <SlidersHorizontal className="h-4 w-4" /> Personalizar
                    </button>
                </div>
            </div>
        </div>
    );
}
