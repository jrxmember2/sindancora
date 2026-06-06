import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import OccurrenceForm, { OccurrenceFormData } from './OccurrenceForm';

interface Option { value: string; label: string }
interface UnitOption extends Option { condominium_id: string }
interface Props {
    condominiums: Option[];
    units: UnitOption[];
    assignableUsers: Option[];
    categories: Record<string, string>;
    priorities: Record<string, string>;
}

export default function OccurrenceCreate({ condominiums, units, assignableUsers, categories, priorities }: Props) {
    const form = useForm<OccurrenceFormData & { attachments: File[] }>({
        condominium_id: condominiums.length === 1 ? condominiums[0].value : '',
        unit_id: '', assigned_to: '', title: '', description: '', category: 'maintenance', priority: 'normal', due_at: '',
        attachments: [],
    });

    return (
        <AppLayout>
            <Head title="Nova Ocorrência" />
            <div className="space-y-4">
                <div>
                    <Link href={route('occurrences.index')} className="text-sm text-gray-500 hover:text-gray-700">← Ocorrências</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Nova Ocorrência</h1>
                </div>
                <OccurrenceForm
                    data={form.data} setData={form.setData} errors={form.errors} processing={form.processing}
                    onSubmit={() => form.post(route('occurrences.store'))}
                    condominiums={condominiums} units={units} assignableUsers={assignableUsers}
                    categories={categories} priorities={priorities}
                    submitLabel="Registrar" backHref={route('occurrences.index')}
                    attachments={form.data.attachments} onAttachmentsChange={(f) => form.setData('attachments', f)}
                />
            </div>
        </AppLayout>
    );
}
