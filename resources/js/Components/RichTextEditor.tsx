import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { useEffect } from 'react';
import { Bold, Italic, List, ListOrdered, Heading2, Quote, Undo, Redo } from 'lucide-react';

interface Props {
    value: string;
    onChange: (html: string) => void;
    placeholder?: string;
}

function ToolbarButton({ active, onClick, title, children }: { active?: boolean; onClick: () => void; title: string; children: React.ReactNode }) {
    return (
        <button
            type="button"
            title={title}
            onClick={onClick}
            className={`rounded p-1.5 text-sm transition-colors ${active ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700'}`}
        >
            {children}
        </button>
    );
}

export default function RichTextEditor({ value, onChange }: Props) {
    const editor = useEditor({
        extensions: [StarterKit],
        content: value,
        editorProps: {
            attributes: {
                class: 'rich-content max-w-none min-h-[180px] px-3 py-2 focus:outline-none',
            },
        },
        onUpdate: ({ editor }) => {
            const html = editor.getHTML();
            onChange(html === '<p></p>' ? '' : html);
        },
    });

    // Mantém o editor em sincronia quando o valor muda externamente (ex.: carregar para edição).
    useEffect(() => {
        if (editor && value !== editor.getHTML()) {
            editor.commands.setContent(value || '', { emitUpdate: false });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [editor]);

    if (!editor) return null;

    return (
        <div className="rounded-lg border border-gray-200 focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500">
            <div className="flex flex-wrap items-center gap-1 border-b border-gray-100 px-2 py-1.5">
                <ToolbarButton title="Negrito" active={editor.isActive('bold')} onClick={() => editor.chain().focus().toggleBold().run()}>
                    <Bold className="h-4 w-4" />
                </ToolbarButton>
                <ToolbarButton title="Itálico" active={editor.isActive('italic')} onClick={() => editor.chain().focus().toggleItalic().run()}>
                    <Italic className="h-4 w-4" />
                </ToolbarButton>
                <ToolbarButton title="Título" active={editor.isActive('heading', { level: 2 })} onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}>
                    <Heading2 className="h-4 w-4" />
                </ToolbarButton>
                <span className="mx-1 h-5 w-px bg-gray-200" />
                <ToolbarButton title="Lista" active={editor.isActive('bulletList')} onClick={() => editor.chain().focus().toggleBulletList().run()}>
                    <List className="h-4 w-4" />
                </ToolbarButton>
                <ToolbarButton title="Lista numerada" active={editor.isActive('orderedList')} onClick={() => editor.chain().focus().toggleOrderedList().run()}>
                    <ListOrdered className="h-4 w-4" />
                </ToolbarButton>
                <ToolbarButton title="Citação" active={editor.isActive('blockquote')} onClick={() => editor.chain().focus().toggleBlockquote().run()}>
                    <Quote className="h-4 w-4" />
                </ToolbarButton>
                <span className="mx-1 h-5 w-px bg-gray-200" />
                <ToolbarButton title="Desfazer" onClick={() => editor.chain().focus().undo().run()}>
                    <Undo className="h-4 w-4" />
                </ToolbarButton>
                <ToolbarButton title="Refazer" onClick={() => editor.chain().focus().redo().run()}>
                    <Redo className="h-4 w-4" />
                </ToolbarButton>
            </div>
            <EditorContent editor={editor} />
        </div>
    );
}
