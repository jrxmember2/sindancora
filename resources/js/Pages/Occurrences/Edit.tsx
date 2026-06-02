import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import OccurrenceForm, { OccurrenceFormData } from './OccurrenceForm';

interface Option { value: string; label: string }
interface UnitOption extends Option { condominium_id: string }
interface Occurrence {
    id: string; condominium_id: string; unit_id: string | null; assigned_to: string | null;
    title: string; description: string; category: string; priority: string;
}
interface Props {
    occurrence: Occurrence;
    condominiums: Option[];
    units: UnitOption[];
    assignableUsers: Option[];
    categories: Record<string, string>;
    priorities: Record<string, string>;
}

export default function OccurrenceEdit({ occurrence, condominiums, units, assignableUsers, categories, priorities }: Props) {
    const form = useForm<OccurrenceFormData>({
        condominium_id: occurrence.condominium_id,
        unit_id: occurrence.unit_id ?? '',
        assigned_to: occurrence.assigned_to ?? '',
        title: occurrence.title,
        description: occurrence.description,
        category: occurrence.category,
        priority: occurrence.priority,
    });

    return (
        <AppLayout>
            <Head title="Editar Ocorrência" />
            <div className="space-y-4">
                <div>
                    <Link href={route('occurrences.show', occurrence.id)} className="text-sm text-gray-500 hover:text-gray-700">← Ocorrência</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Editar Ocorrência</h1>
                </div>
                <OccurrenceForm
                    data={form.data} setData={form.setData} errors={form.errors} processing={form.processing}
                    onSubmit={() => form.put(route('occurrences.update', occurrence.id))}
                    condominiums={condominiums} units={units} assignableUsers={assignableUsers}
                    categories={categories} priorities={priorities}
                    submitLabel="Salvar" backHref={route('occurrences.show', occurrence.id)}
                />
            </div>
        </AppLayout>
    );
}
