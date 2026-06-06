import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import MaintenanceForm, { MaintenanceFormData } from './MaintenanceForm';

interface Option { value: string; label: string }
interface Plan {
    id: string; condominium_id: string; supplier_id: string | null; category: string | null;
    title: string; description: string | null; frequency: string; next_due_date: string | null;
    alert_days: number; is_active: boolean;
}
interface Props {
    plan: Plan;
    categories: Record<string, string>;
    frequencies: Record<string, string>;
    condominiums: Option[];
    suppliers: Option[];
}

export default function MaintenanceEdit({ plan, categories, frequencies, condominiums, suppliers }: Props) {
    const form = useForm<MaintenanceFormData>({
        condominium_id: plan.condominium_id,
        supplier_id: plan.supplier_id ?? '',
        category: plan.category ?? '',
        title: plan.title,
        description: plan.description ?? '',
        frequency: plan.frequency,
        next_due_date: plan.next_due_date ? plan.next_due_date.slice(0, 10) : '',
        alert_days: String(plan.alert_days ?? 15),
        is_active: plan.is_active,
    });

    return (
        <AppLayout>
            <Head title="Editar Manutenção" />
            <div className="space-y-4">
                <div>
                    <Link href={route('maintenance.show', plan.id)} className="text-sm text-gray-500 hover:text-gray-700">← {plan.title}</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Editar Manutenção</h1>
                </div>
                <MaintenanceForm
                    data={form.data} setData={(k, v) => form.setData(k, v)} errors={form.errors} processing={form.processing}
                    onSubmit={() => form.put(route('maintenance.update', plan.id))}
                    submitLabel="Salvar" backHref={route('maintenance.show', plan.id)}
                    categories={categories} frequencies={frequencies} condominiums={condominiums} suppliers={suppliers}
                />
            </div>
        </AppLayout>
    );
}
