import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import WorkForm, { type Option, type ProposalOption, type WorkFormData } from './WorkForm';

interface Props {
    types: Record<string, string>;
    statuses: Record<string, string>;
    priorities: Record<string, string>;
    condominiums: Option[];
    suppliers: Option[];
    approvedProposals: ProposalOption[];
}

export default function WorkCreate({ types, statuses, priorities, condominiums, suppliers, approvedProposals }: Props) {
    const { data, setData, post, processing, errors } = useForm<WorkFormData>({
        condominium_id: '',
        supplier_id: '',
        quotation_proposal_id: '',
        title: '',
        type: 'renovation',
        status: 'planned',
        priority: 'normal',
        description: '',
        start_date: '',
        expected_end_date: '',
        budget_amount: '',
        final_amount: '',
        progress_percent: '0',
        responsible_name: '',
        notes: '',
        attachments: [],
    });

    return (
        <AppLayout>
            <Head title="Nova obra/reforma" />
            <div className="space-y-4">
                <div>
                    <Link href={route('works.index')} className="text-sm text-gray-500 hover:text-gray-700">← Obras</Link>
                    <h1 className="mt-1 text-2xl font-bold text-gray-900">Nova obra/reforma</h1>
                </div>
                <WorkForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    processing={processing}
                    onSubmit={() => post(route('works.store'), { forceFormData: true })}
                    submitLabel="Criar obra"
                    backHref={route('works.index')}
                    types={types}
                    statuses={statuses}
                    priorities={priorities}
                    condominiums={condominiums}
                    suppliers={suppliers}
                    approvedProposals={approvedProposals}
                />
            </div>
        </AppLayout>
    );
}
