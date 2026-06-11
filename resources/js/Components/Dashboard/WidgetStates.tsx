import { Inbox, AlertOctagon } from 'lucide-react';

/** Skeleton de carregamento — usado enquanto um widget lazy busca dados. */
export function WidgetSkeleton() {
    return (
        <div className="animate-pulse space-y-3">
            <div className="h-3 w-1/3 rounded bg-gray-100" />
            <div className="h-8 w-2/3 rounded bg-gray-100" />
            <div className="h-24 w-full rounded bg-gray-50" />
        </div>
    );
}

/** Estado vazio bonito. */
export function EmptyWidgetState({ text }: { text?: string }) {
    return (
        <div className="flex flex-col items-center justify-center py-8 text-center">
            <div className="rounded-full bg-gray-50 p-3">
                <Inbox className="h-6 w-6 text-gray-300" />
            </div>
            <p className="mt-3 text-sm text-gray-400">{text ?? 'Sem dados para o período.'}</p>
        </div>
    );
}

/** Estado de erro. */
export function ErrorWidgetState({ message, onRetry }: { message?: string; onRetry?: () => void }) {
    return (
        <div className="flex flex-col items-center justify-center py-8 text-center">
            <div className="rounded-full bg-red-50 p-3">
                <AlertOctagon className="h-6 w-6 text-red-400" />
            </div>
            <p className="mt-3 text-sm text-gray-500">{message ?? 'Não foi possível carregar.'}</p>
            {onRetry && (
                <button
                    onClick={onRetry}
                    className="mt-3 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50"
                >
                    Tentar novamente
                </button>
            )}
        </div>
    );
}
