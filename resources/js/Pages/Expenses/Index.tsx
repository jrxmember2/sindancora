import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Pencil, Trash2, Download, Receipt } from 'lucide-react';

interface Expense {
    id: string; category: string; description: string; amount: string; expense_date: string;
    supplier: string | null; receipt_storage_object_id: string | null;
    condominium: { name: string } | null;
}
interface Props {
    expenses: { data: Expense[] };
    total: string | number;
    condominiums: { value: string; label: string }[];
    categories: Record<string, string>;
    filters: { condominium_id?: string; category?: string; from?: string; to?: string };
}

const brl = (v: string | number) => Number(v ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function ExpensesIndex({ expenses, total, condominiums, categories, filters }: Props) {
    const apply = (params: Record<string, string>) => router.get(route('expenses.index'), { ...filters, ...params }, { preserveState: true, replace: true });
    const remove = (id: string) => { if (confirm('Remover esta despesa?')) router.delete(route('expenses.destroy', id)); };

    return (
        <AppLayout>
            <Head title="Despesas" />

            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Despesas</h1>
                    <p className="text-sm text-gray-500">Total no filtro: <span className="font-semibold text-gray-700">{brl(total)}</span></p>
                </div>
                <Link href={route('expenses.create')} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    <Plus className="h-4 w-4" /> Nova despesa
                </Link>
            </div>

            <div className="mb-4 flex flex-col gap-2 sm:flex-row">
                <select value={filters.condominium_id ?? ''} onChange={(e) => apply({ condominium_id: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="">Todos os condomínios</option>
                    {condominiums.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                </select>
                <select value={filters.category ?? ''} onChange={(e) => apply({ category: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    <option value="">Todas as categorias</option>
                    {Object.entries(categories).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
                <input type="date" value={filters.from ?? ''} onChange={(e) => apply({ from: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                <input type="date" value={filters.to ?? ''} onChange={(e) => apply({ to: e.target.value })} className="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
            </div>

            <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <table className="w-full text-sm">
                    <thead className="border-b border-gray-100 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th className="px-4 py-3">Descrição</th>
                            <th className="px-4 py-3">Categoria</th>
                            <th className="px-4 py-3">Data</th>
                            <th className="px-4 py-3 text-right">Valor</th>
                            <th className="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50">
                        {expenses.data.length === 0 && (
                            <tr><td colSpan={5} className="px-4 py-10 text-center text-gray-400"><Receipt className="mx-auto mb-2 h-8 w-8 text-gray-300" />Nenhuma despesa lançada.</td></tr>
                        )}
                        {expenses.data.map((e) => (
                            <tr key={e.id} className="hover:bg-gray-50">
                                <td className="px-4 py-3">
                                    <p className="font-medium text-gray-900">{e.description}</p>
                                    <p className="text-xs text-gray-500">{e.condominium?.name}{e.supplier ? ` · ${e.supplier}` : ''}</p>
                                </td>
                                <td className="px-4 py-3 text-gray-600">{categories[e.category] ?? e.category}</td>
                                <td className="px-4 py-3 text-gray-600">{new Date(e.expense_date + 'T00:00:00').toLocaleDateString('pt-BR')}</td>
                                <td className="px-4 py-3 text-right font-medium text-gray-900">{brl(e.amount)}</td>
                                <td className="px-4 py-3 text-right">
                                    <div className="flex items-center justify-end gap-1">
                                        {e.receipt_storage_object_id && (
                                            <a href={route('expenses.download', e.id)} className="rounded p-1 text-gray-400 hover:text-blue-600" title="Comprovante"><Download className="h-4 w-4" /></a>
                                        )}
                                        <Link href={route('expenses.edit', e.id)} className="rounded p-1 text-gray-400 hover:text-blue-600" title="Editar"><Pencil className="h-4 w-4" /></Link>
                                        <button onClick={() => remove(e.id)} className="rounded p-1 text-gray-400 hover:text-red-500" title="Remover"><Trash2 className="h-4 w-4" /></button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
