import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import MaintenanceForm, { MaintenanceFormData } from './MaintenanceForm';

interface Option { value: string; label: string }
interface Props {
    categories: Record<string, string>;
    frequencies: Record<string, string>;
    condominiums: Option[];
    suppliers: Option[];
}

export default function MaintenanceCreate({ categories, frequencies, condominiums, suppliers }: Props) {
    const form = useForm<MaintenanceFormData>({
        condominium_id: condominiums.length === 1 ? condominiums[0].value : '',
        supplier_id: '', category: '', title: '', description: '',
        frequency: 'annual', next_due_date: '', alert_days: '15', is_active: true,
    });

    return (
        <AppLayout>
            <Head title="Nova Manutenção" />
            <div className="space-y-4">
                <div>
                    <Link href={route('maintenance.index')} className="text-sm text-gray-500 hover:text-gray-700">← Manutenção</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Nova Manutenção</h1>
                </div>
                <MaintenanceForm
                    data={form.data} setData={(k, v) => form.setData(k, v)} errors={form.errors} processing={form.processing}
                    onSubmit={() => form.post(route('maintenance.store'))}
                    submitLabel="Cadastrar" backHref={route('maintenance.index')}
                    categories={categories} frequencies={frequencies} condominiums={condominiums} suppliers={suppliers}
                />
            </div>
        </AppLayout>
    );
}
