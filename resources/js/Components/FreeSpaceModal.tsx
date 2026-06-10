import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Trash2, X, Loader2, Smartphone } from 'lucide-react';

const OPTIONS = [
    { label: '25%', value: 0.25, hint: 'as mais antigas' },
    { label: '50%', value: 0.5, hint: 'metade' },
    { label: '100%', value: 1, hint: 'tudo' },
];

export default function FreeSpaceModal({ open, onClose }: { open: boolean; onClose: () => void }) {
    const [submitting, setSubmitting] = useState<number | null>(null);

    if (!open) return null;

    const free = (fraction: number) => {
        setSubmitting(fraction);
        router.post(
            route('settings.storage.free'),
            { fraction },
            {
                preserveScroll: true,
                onSuccess: () => onClose(),
                onFinish: () => setSubmitting(null),
            },
        );
    };

    return (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4" onClick={onClose}>
            <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-start justify-between">
                    <h2 className="flex items-center gap-2 text-lg font-bold text-gray-900">
                        <Trash2 className="h-5 w-5 text-red-600" /> Liberar espaço
                    </h2>
                    <button onClick={onClose} className="rounded p-1 text-gray-400 hover:text-gray-600">
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <p className="mt-2 text-sm text-gray-600">
                    Remova a mídia mais antiga do WhatsApp (imagens, áudios, vídeos e documentos do atendimento)
                    para liberar espaço no seu plano. Escolha quanto remover:
                </p>

                <div className="mt-4 grid grid-cols-3 gap-3">
                    {OPTIONS.map((opt) => (
                        <button
                            key={opt.value}
                            onClick={() => free(opt.value)}
                            disabled={submitting !== null}
                            className="flex flex-col items-center gap-1 rounded-xl border border-gray-200 px-3 py-4 transition hover:border-red-300 hover:bg-red-50 disabled:opacity-50"
                        >
                            {submitting === opt.value ? (
                                <Loader2 className="h-5 w-5 animate-spin text-red-600" />
                            ) : (
                                <span className="text-xl font-bold text-gray-900">{opt.label}</span>
                            )}
                            <span className="text-xs text-gray-500">{opt.hint}</span>
                        </button>
                    ))}
                </div>

                <div className="mt-4 flex gap-2 rounded-lg bg-blue-50 p-3 text-xs text-blue-800">
                    <Smartphone className="h-4 w-4 flex-shrink-0" />
                    <span>
                        Os arquivos são removidos <strong>apenas do sistema</strong>. Eles continuam no celular de
                        quem enviou ou recebeu as mensagens no WhatsApp.
                    </span>
                </div>
            </div>
        </div>
    );
}
