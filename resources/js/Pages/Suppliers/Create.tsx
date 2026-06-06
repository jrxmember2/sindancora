import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import SupplierForm, { SupplierFormData } from './SupplierForm';

interface Option { value: string; label: string }

export default function SupplierCreate({ categories, condominiums }: { categories: Record<string, string>; condominiums: Option[] }) {
    const form = useForm<SupplierFormData>({
        name: '', category: '', document: '', contact_name: '', phone: '', email: '', website: '',
        zip_code: '', street: '', number: '', complement: '', neighborhood: '', city: '', state: '',
        notes: '', is_active: true, condominium_ids: [],
    });

    return (
        <AppLayout>
            <Head title="Novo Fornecedor" />
            <div className="space-y-4">
                <div>
                    <Link href={route('suppliers.index')} className="text-sm text-gray-500 hover:text-gray-700">← Fornecedores</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Novo Fornecedor</h1>
                </div>
                <SupplierForm
                    data={form.data} setData={(k, v) => form.setData(k, v)} errors={form.errors} processing={form.processing}
                    onSubmit={() => form.post(route('suppliers.store'))}
                    submitLabel="Cadastrar" backHref={route('suppliers.index')}
                    categories={categories} condominiums={condominiums}
                />
            </div>
        </AppLayout>
    );
}
