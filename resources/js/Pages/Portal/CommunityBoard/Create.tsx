import PortalLayout from '@/Layouts/PortalLayout';
import { Head, useForm } from '@inertiajs/react';

interface Option { value: string; label: string }

export default function Create({ condominiums, categories, defaults }: { condominiums: Option[]; categories: Record<string, string>; defaults: { contact_name: string | null; contact_phone: string | null; contact_email: string | null } }) {
    const form = useForm({
        condominium_id: condominiums[0]?.value ?? '',
        category: 'sale',
        title: '',
        body: '',
        price: '',
        contact_name: defaults.contact_name ?? '',
        contact_phone: defaults.contact_phone ?? '',
        contact_email: defaults.contact_email ?? '',
        expires_at: '',
        attachments: [] as File[],
    });

    const input = 'w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500';

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('portal.community-board.store'), { forceFormData: true });
    };

    return (
        <PortalLayout title="Enviar classificado">
            <Head title="Enviar classificado" />
            <form onSubmit={submit} className="space-y-4">
                <div className="space-y-4 rounded-xl border border-gray-100 bg-white p-5">
                    {condominiums.length > 1 && (
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Condominio</label>
                            <select value={form.data.condominium_id} onChange={(e) => form.setData('condominium_id', e.target.value)} className={input}>
                                {condominiums.map((condominium) => <option key={condominium.value} value={condominium.value}>{condominium.label}</option>)}
                            </select>
                        </div>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Categoria</label>
                            <select value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} className={input}>
                                {Object.entries(categories).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Preco</label>
                            <input type="number" min="0" step="0.01" value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} className={input} />
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Titulo</label>
                        <input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} className={input} placeholder="Ex.: Bicicleta aro 29" />
                        {form.errors.title && <p className="mt-1 text-xs text-red-600">{form.errors.title}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-gray-700">Descricao</label>
                        <textarea value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} rows={5} className={input} />
                        {form.errors.body && <p className="mt-1 text-xs text-red-600">{form.errors.body}</p>}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Nome para contato</label>
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

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Expira em</label>
                            <input type="date" value={form.data.expires_at} onChange={(e) => form.setData('expires_at', e.target.value)} className={input} />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-gray-700">Imagem/anexo</label>
                            <input type="file" multiple accept="image/png,image/jpeg,image/webp,application/pdf" onChange={(e) => form.setData('attachments', Array.from(e.target.files ?? []))} className="block w-full text-sm" />
                        </div>
                    </div>
                </div>

                <button type="submit" disabled={form.processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    {form.processing ? 'Enviando...' : 'Enviar para moderacao'}
                </button>
            </form>
        </PortalLayout>
    );
}
