import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Truck, Pencil, Star, Trash2, Mail, Phone, Globe, MapPin } from 'lucide-react';
import { maskCpfCnpj } from '@/lib/masks';
import type { PageProps } from '@/types';

interface Evaluation {
    id: string; score: number; comment: string | null; created_at: string;
    author: { id: string; name: string } | null;
}
interface Supplier {
    id: string; name: string; category: string | null; document: string | null;
    contact_name: string | null; phone: string | null; email: string | null; website: string | null;
    zip_code: string | null; street: string | null; number: string | null; complement: string | null;
    neighborhood: string | null; city: string | null; state: string | null; notes: string | null;
    is_active: boolean;
    condominiums: { id: string; name: string }[];
    evaluations: Evaluation[];
    evaluations_count: number;
    evaluations_avg_score: number | null;
}
interface Props { supplier: Supplier; categories: Record<string, string> }

function Stars({ value, className = 'h-4 w-4' }: { value: number; className?: string }) {
    return (
        <span className="inline-flex items-center">
            {[1, 2, 3, 4, 5].map(i => (
                <Star key={i} className={`${className} ${i <= value ? 'fill-amber-400 text-amber-400' : 'text-gray-300'}`} />
            ))}
        </span>
    );
}

function fmtDate(iso: string) {
    return new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

export default function SupplierShow({ supplier, categories }: Props) {
    const { auth } = usePage<PageProps>().props;
    const perms = auth.user?.permissions ?? [];
    const can = (p: string) => perms.includes('*') || perms.includes(p);

    const form = useForm({ score: 5, comment: '' });
    const submitEvaluation = () => form.post(route('suppliers.evaluations.store', supplier.id), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
    const removeEvaluation = (id: string) => {
        if (confirm('Remover esta avaliação?')) router.delete(route('suppliers.evaluations.destroy', id), { preserveScroll: true });
    };

    const address = [supplier.street, supplier.number, supplier.neighborhood, supplier.city, supplier.state]
        .filter(Boolean).join(', ');

    return (
        <AppLayout>
            <Head title={supplier.name} />
            <div className="mx-auto max-w-3xl space-y-6">
                <div className="flex items-start justify-between">
                    <div>
                        <Link href={route('suppliers.index')} className="text-sm text-gray-500 hover:text-gray-700">← Fornecedores</Link>
                        <div className="mt-1 flex items-center gap-2">
                            <Truck className="h-6 w-6 text-blue-600" />
                            <h1 className="text-2xl font-bold text-gray-900">{supplier.name}</h1>
                            {!supplier.is_active && <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">inativo</span>}
                        </div>
                    </div>
                    {can('suppliers:update') && (
                        <Link href={route('suppliers.edit', supplier.id)} className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                            <Pencil className="h-4 w-4" /> Editar
                        </Link>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <p className="text-xs uppercase tracking-wide text-gray-500">Categoria</p>
                        <p className="mt-1 text-sm font-medium text-gray-900">{supplier.category ? (categories[supplier.category] ?? supplier.category) : '—'}</p>
                    </div>
                    <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <p className="text-xs uppercase tracking-wide text-gray-500">CPF / CNPJ</p>
                        <p className="mt-1 text-sm font-medium text-gray-900">{supplier.document ? maskCpfCnpj(supplier.document) : '—'}</p>
                    </div>
                    <div className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                        <p className="text-xs uppercase tracking-wide text-gray-500">Avaliação média</p>
                        <div className="mt-1 flex items-center gap-2">
                            {supplier.evaluations_count && supplier.evaluations_avg_score !== null ? (
                                <>
                                    <Stars value={Math.round(supplier.evaluations_avg_score)} />
                                    <span className="text-sm text-gray-600">{Number(supplier.evaluations_avg_score).toFixed(1)} ({supplier.evaluations_count})</span>
                                </>
                            ) : <span className="text-sm text-gray-400">sem avaliação</span>}
                        </div>
                    </div>
                </div>

                <div className="space-y-3 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-700">Contato</h2>
                    <div className="grid gap-2 text-sm text-gray-700 sm:grid-cols-2">
                        {supplier.contact_name && <p>👤 {supplier.contact_name}</p>}
                        {supplier.phone && <p className="flex items-center gap-2"><Phone className="h-4 w-4 text-gray-400" /> {supplier.phone}</p>}
                        {supplier.email && <p className="flex items-center gap-2"><Mail className="h-4 w-4 text-gray-400" /> {supplier.email}</p>}
                        {supplier.website && <p className="flex items-center gap-2"><Globe className="h-4 w-4 text-gray-400" /> <a href={supplier.website} target="_blank" rel="noreferrer" className="text-blue-600 hover:underline">{supplier.website}</a></p>}
                        {address && <p className="flex items-center gap-2"><MapPin className="h-4 w-4 text-gray-400" /> {address}</p>}
                    </div>
                    {supplier.condominiums.length > 0 && (
                        <div className="pt-2">
                            <p className="text-xs uppercase tracking-wide text-gray-500">Condomínios atendidos</p>
                            <div className="mt-1 flex flex-wrap gap-1">
                                {supplier.condominiums.map(c => <span key={c.id} className="rounded-full bg-blue-50 px-2 py-0.5 text-xs text-blue-700">{c.name}</span>)}
                            </div>
                        </div>
                    )}
                    {supplier.notes && <p className="border-t border-gray-100 pt-3 text-sm text-gray-600">{supplier.notes}</p>}
                </div>

                <div className="space-y-4 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-700">Avaliações</h2>

                    {can('suppliers:update') && (
                        <div className="space-y-3 rounded-lg bg-gray-50 p-4">
                            <div className="flex items-center gap-3">
                                <span className="text-sm text-gray-600">Sua nota:</span>
                                {[1, 2, 3, 4, 5].map(i => (
                                    <button key={i} type="button" onClick={() => form.setData('score', i)}>
                                        <Star className={`h-6 w-6 ${i <= form.data.score ? 'fill-amber-400 text-amber-400' : 'text-gray-300'}`} />
                                    </button>
                                ))}
                            </div>
                            <textarea value={form.data.comment} onChange={e => form.setData('comment', e.target.value)} rows={2} placeholder="Comentário (opcional)…" className="w-full resize-none rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                            <button onClick={submitEvaluation} disabled={form.processing} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-50">
                                {form.processing ? 'Salvando…' : 'Avaliar'}
                            </button>
                        </div>
                    )}

                    <div className="divide-y divide-gray-50">
                        {supplier.evaluations.length === 0 && <p className="py-4 text-sm text-gray-500">Nenhuma avaliação ainda.</p>}
                        {supplier.evaluations.map(ev => (
                            <div key={ev.id} className="flex items-start justify-between py-3">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <Stars value={ev.score} className="h-3.5 w-3.5" />
                                        <span className="text-xs text-gray-500">{ev.author?.name ?? 'Usuário'} · {fmtDate(ev.created_at)}</span>
                                    </div>
                                    {ev.comment && <p className="mt-1 text-sm text-gray-700">{ev.comment}</p>}
                                </div>
                                {can('suppliers:delete') && (
                                    <button onClick={() => removeEvaluation(ev.id)} className="rounded p-1.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-500"><Trash2 className="h-4 w-4" /></button>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
