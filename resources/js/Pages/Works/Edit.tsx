import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import WorkForm, { type Attachment, type Option, type ProposalOption, type WorkFormData } from './WorkForm';

interface Work {
    id: string;
    condominium_id: string;
    supplier_id: string | null;
    quotation_proposal_id: string | null;
    title: string;
    type: string;
    status: string;
    priority: string;
    description: string | null;
    start_date: string | null;
    expected_end_date: string | null;
    budget_amount: string | null;
    final_amount: string | null;
    progress_percent: number;
    responsible_name: string | null;
    notes: string | null;
    attachments: Attachment[];
}

interface Props {
    work: Work;
    types: Record<string, string>;
    statuses: Record<string, string>;
    priorities: Record<string, string>;
    condominiums: Option[];
    suppliers: Option[];
    approvedProposals: ProposalOption[];
}

const dateOnly = (value?: string | null) => value?.slice(0, 10) ?? '';

export default function WorkEdit({ work, types, statuses, priorities, condominiums, suppliers, approvedProposals }: Props) {
    const form = useForm<WorkFormData>({
        condominium_id: work.condominium_id,
        supplier_id: work.supplier_id ?? '',
        quotation_proposal_id: work.quotation_proposal_id ?? '',
        title: work.title,
        type: work.type,
        status: work.status,
        priority: work.priority,
        description: work.description ?? '',
        start_date: dateOnly(work.start_date),
        expected_end_date: dateOnly(work.expected_end_date),
        budget_amount: work.budget_amount ?? '',
        final_amount: work.final_amount ?? '',
        progress_percent: String(work.progress_percent ?? 0),
        responsible_name: work.responsible_name ?? '',
        notes: work.notes ?? '',
        attachments: [],
    });

    const submit = () => {
        form.transform((payload) => ({ ...payload, _method: 'patch' }));
        form.post(route('works.update', work.id), { forceFormData: true });
    };

    return (
        <AppLayout>
            <Head title="Editar obra/reforma" />
            <div className="space-y-4">
                <div>
                    <Link href={route('works.show', work.id)} className="text-sm text-gray-500 hover:text-gray-700">← Obra</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Editar obra/reforma</h1>
                </div>
                <WorkForm
                    data={form.data}
                    setData={form.setData}
                    errors={form.errors}
                    processing={form.processing}
                    onSubmit={submit}
                    submitLabel="Salvar alterações"
                    backHref={route('works.show', work.id)}
                    types={types}
                    statuses={statuses}
                    priorities={priorities}
                    condominiums={condominiums}
                    suppliers={suppliers}
                    approvedProposals={approvedProposals}
                    existingAttachments={work.attachments}
                />
            </div>
        </AppLayout>
    );
}
