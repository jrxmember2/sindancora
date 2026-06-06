import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import SupplierForm, { SupplierFormData } from './SupplierForm';

interface Option { value: string; label: string }

interface Supplier extends Partial<SupplierFormData> {
    id: string;
    condominium_ids: string[];
}

export default function SupplierEdit({ supplier, categories, condominiums }: { supplier: Supplier; categories: Record<string, string>; condominiums: Option[] }) {
    const form = useForm<SupplierFormData>({
        name: supplier.name ?? '',
        category: supplier.category ?? '',
        document: supplier.document ?? '',
        contact_name: supplier.contact_name ?? '',
        phone: supplier.phone ?? '',
        email: supplier.email ?? '',
        website: supplier.website ?? '',
        zip_code: supplier.zip_code ?? '',
        street: supplier.street ?? '',
        number: supplier.number ?? '',
        complement: supplier.complement ?? '',
        neighborhood: supplier.neighborhood ?? '',
        city: supplier.city ?? '',
        state: supplier.state ?? '',
        notes: supplier.notes ?? '',
        is_active: supplier.is_active ?? true,
        condominium_ids: supplier.condominium_ids ?? [],
    });

    return (
        <AppLayout>
            <Head title="Editar Fornecedor" />
            <div className="space-y-4">
                <div>
                    <Link href={route('suppliers.show', supplier.id)} className="text-sm text-gray-500 hover:text-gray-700">← {supplier.name}</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Editar Fornecedor</h1>
                </div>
                <SupplierForm
                    data={form.data} setData={(k, v) => form.setData(k, v)} errors={form.errors} processing={form.processing}
                    onSubmit={() => form.put(route('suppliers.update', supplier.id))}
                    submitLabel="Salvar" backHref={route('suppliers.show', supplier.id)}
                    categories={categories} condominiums={condominiums}
                />
            </div>
        </AppLayout>
    );
}
