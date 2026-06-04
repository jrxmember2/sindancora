import { useRef } from 'react';
import { Paperclip, X, FileText, Image as ImageIcon } from 'lucide-react';

interface Props {
    value: File[];
    onChange: (files: File[]) => void;
    error?: string;
    label?: string;
    hint?: string;
}

function humanSize(bytes: number): string {
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export default function AttachmentInput({ value, onChange, error, label = 'Anexos', hint }: Props) {
    const inputRef = useRef<HTMLInputElement>(null);

    const add = (files: FileList | null) => {
        if (!files) return;
        onChange([...value, ...Array.from(files)]);
        if (inputRef.current) inputRef.current.value = '';
    };

    const remove = (index: number) => onChange(value.filter((_, i) => i !== index));

    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>

            <button
                type="button"
                onClick={() => inputRef.current?.click()}
                className="flex w-full items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-300 px-4 py-3 text-sm text-gray-500 transition-colors hover:border-blue-400 hover:text-blue-600"
            >
                <Paperclip className="h-4 w-4" /> Adicionar arquivos
            </button>
            <input
                ref={inputRef}
                type="file"
                multiple
                className="hidden"
                accept=".pdf,.doc,.docx,.xls,.xlsx,.odt,.ods,.jpg,.jpeg,.png,.webp,.gif,.zip"
                onChange={(e) => add(e.target.files)}
            />

            {hint && <p className="mt-1 text-xs text-gray-400">{hint}</p>}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}

            {value.length > 0 && (
                <ul className="mt-2 space-y-1">
                    {value.map((file, i) => {
                        const isImage = file.type.startsWith('image/');
                        return (
                            <li key={i} className="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                                {isImage ? <ImageIcon className="h-4 w-4 flex-shrink-0 text-gray-400" /> : <FileText className="h-4 w-4 flex-shrink-0 text-gray-400" />}
                                <span className="min-w-0 flex-1 truncate text-gray-700">{file.name}</span>
                                <span className="flex-shrink-0 text-xs text-gray-400">{humanSize(file.size)}</span>
                                <button type="button" onClick={() => remove(i)} className="flex-shrink-0 text-gray-400 hover:text-red-500">
                                    <X className="h-4 w-4" />
                                </button>
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}
