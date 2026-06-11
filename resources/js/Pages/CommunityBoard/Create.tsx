import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';

interface Option { value: string; label: string }

export default function Create({ condominiums, types, categories }: { condominiums: Option[]; types: Record<string, string>; categories: Record<string, string> }) {
    const form = useForm({
        condominium_id: condominiums[0]?.value ?? '',
        post_type: 'notice',
        category: 'notice',
        title: '',
        body: '',
        price: '',
        contact_name: '',
        contact_phone: '',
        contact_email: '',
        expires_at: '',
        publish: true,
        attachments: [] as File[],
    });

    const input = 'w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500';

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('community-board.store'), { forceFormData: true });
    };

    return (
        <AppLayout>
            <Head title="Nova publicacao" />
            <form onSubmit={submit} className="mx-auto max-w-3xl space-y-5">
                <h1 className="text-2xl font-bold text-gray-900">Nova publicacao</h1>

                <div className="space-y-4 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Condominio</label>
                            <select value={form.data.condominium_id} onChange={(e) => form.setData('condominium_id', e.target.value)} className={input}>
                                {condominiums.map((condominium) => <option key={condominium.value} value={condominium.value}>{condominium.label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Tipo</label>
                            <select value={form.data.post_type} onChange={(e) => form.setData('post_type', e.target.value)} className={input}>
                                {Object.entries(types).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Categoria</label>
                            <select value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} className={input}>
                                {Object.entries(categories).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
                            </select>
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Titulo</label>
                        <input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} className={input} />
                        {form.errors.title && <p className="mt-1 text-xs text-red-600">{form.errors.title}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Texto</label>
                        <textarea value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} rows={6} className={input} />
                        {form.errors.body && <p className="mt-1 text-xs text-red-600">{form.errors.body}</p>}
                    </div>

                    {form.data.post_type === 'classified' && (
                        <div className="grid gap-4 sm:grid-cols-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Preco</label>
                                <input type="number" min="0" step="0.01" value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} className={input} />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Contato</label>
                                <input value={form.data.contact_name} onChange={(e) => form.setData('contact_name', e.target.value)} className={input} />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">Telefone</label>
                                <input value={form.data.contact_phone} onChange={(e) => form.setData('contact_phone', e.target.value)} className={input} />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700">E-mail</label>
                                <input type="email" value={form.data.contact_email} onChange={(e) => form.setData('contact_email', e.target.value)} className={input} />
                            </div>
                        </div>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Expira em</label>
                            <input type="date" value={form.data.expires_at} onChange={(e) => form.setData('expires_at', e.target.value)} className={input} />
                        </div>
                        <label className="flex items-end gap-2 pb-2 text-sm text-gray-700">
                            <input type="checkbox" checked={form.data.publish} onChange={(e) => form.setData('publish', e.target.checked)} className="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                            Publicar imediatamente
                        </label>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Imagens/anexos</label>
                        <input type="file" multiple accept="image/png,image/jpeg,image/webp,application/pdf" onChange={(e) => form.setData('attachments', Array.from(e.target.files ?? []))} className="block w-full text-sm" />
                    </div>
                </div>

                <button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    {form.processing ? 'Salvando...' : 'Salvar publicacao'}
                </button>
            </form>
        </AppLayout>
    );
}
