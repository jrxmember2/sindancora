import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { UserX, ArrowLeft, Plus, Trash2 } from 'lucide-react';

interface OptOut { id: string; phone: string; reason: string | null; created_at: string | null }
interface Props { optOuts: OptOut[] }

const time = (iso: string | null) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '');

export default function OptOuts({ optOuts }: Props) {
    const form = useForm({ phone: '' });

    const add = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('campaigns.optouts.store'), { preserveScroll: true, onSuccess: () => form.reset() });
    };
    const remove = (o: OptOut) => confirm(`Remover ${o.phone} do descadastro?`) && router.delete(route('campaigns.optouts.destroy', o.id), { preserveScroll: true });

    return (
        <AppLayout>
            <Head title="Descadastros" />

            <div className="mx-auto max-w-2xl space-y-6">
                <Link href={route('campaigns.index')} className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                    <ArrowLeft className="h-4 w-4" /> Disparos
                </Link>
                <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900">
                    <UserX className="h-6 w-6 text-blue-600" /> Descadastros (opt-out)
                </h1>
                <p className="text-sm text-gray-500">
                    Telefones nesta lista são ignorados em todos os disparos. Contatos que respondem <b>SAIR</b> ou <b>PARAR</b> entram aqui automaticamente.
                </p>

                <form onSubmit={add} className="flex items-end gap-3 rounded-xl border border-gray-200 bg-white p-4">
                    <div className="flex-1">
                        <label className="mb-1 block text-sm font-medium text-gray-700">Adicionar telefone</label>
                        <input type="text" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} placeholder="(11) 90000-0000" className="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500" />
                        {form.errors.phone && <p className="mt-1 text-xs text-red-600">{form.errors.phone}</p>}
                    </div>
                    <button type="submit" disabled={form.processing || !form.data.phone.trim()} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        <Plus className="h-4 w-4" /> Adicionar
                    </button>
                </form>

                {optOuts.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-gray-300 bg-white py-12 text-center text-sm text-gray-400">
                        Nenhum descadastro.
                    </div>
                ) : (
                    <ul className="divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white">
                        {optOuts.map((o) => (
                            <li key={o.id} className="flex items-center gap-3 px-4 py-2.5">
                                <span className="font-mono text-sm text-gray-800">{o.phone}</span>
                                <span className="min-w-0 flex-1 truncate text-xs text-gray-400">{o.reason}{o.created_at ? ` · ${time(o.created_at)}` : ''}</span>
                                <button onClick={() => remove(o)} className="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"><Trash2 className="h-4 w-4" /></button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}
