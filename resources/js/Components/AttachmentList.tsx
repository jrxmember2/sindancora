import { router } from '@inertiajs/react';
import { Download, Trash2, FileText, Image as ImageIcon } from 'lucide-react';

export interface Attachment {
    id: string;
    name: string;
    size_mb: number;
    mime: string;
    is_image: boolean;
}

interface Props {
    attachments: Attachment[];
    canRemove?: boolean;
    emptyText?: string;
}

export default function AttachmentList({ attachments, canRemove = false, emptyText }: Props) {
    if (attachments.length === 0) {
        return emptyText ? <p className="text-sm text-gray-400">{emptyText}</p> : null;
    }

    const remove = (id: string) => {
        if (confirm('Remover este anexo?')) {
            router.delete(route('attachments.destroy', id), { preserveScroll: true });
        }
    };

    return (
        <ul className="space-y-1">
            {attachments.map((a) => (
                <li key={a.id} className="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                    {a.is_image ? <ImageIcon className="h-4 w-4 flex-shrink-0 text-gray-400" /> : <FileText className="h-4 w-4 flex-shrink-0 text-gray-400" />}
                    <span className="min-w-0 flex-1 truncate text-gray-700">{a.name}</span>
                    <span className="flex-shrink-0 text-xs text-gray-400">{a.size_mb} MB</span>
                    <a
                        href={route('attachments.download', a.id)}
                        className="flex-shrink-0 text-gray-400 hover:text-blue-600"
                        title="Baixar"
                    >
                        <Download className="h-4 w-4" />
                    </a>
                    {canRemove && (
                        <button type="button" onClick={() => remove(a.id)} className="flex-shrink-0 text-gray-400 hover:text-red-500" title="Remover">
                            <Trash2 className="h-4 w-4" />
                        </button>
                    )}
                </li>
            ))}
        </ul>
    );
}
