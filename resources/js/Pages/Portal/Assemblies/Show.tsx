import PortalLayout from '@/Layouts/PortalLayout';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Vote, CheckCircle2 } from 'lucide-react';

interface Opt { id: string; label: string }
interface Item { id: string; title: string; description: string | null; options: Opt[] }
interface Assembly {
    id: string; title: string; description: string | null; status: string; scheduled_at: string | null;
    minutes: string | null; condominium: { name: string } | null; items: Item[];
}
interface Props {
    assembly: Assembly;
    myVotes: Record<string, string>; // agenda_item_id -> option_id
    present: boolean;
    unitCount: number;
}

export default function PortalAssemblyShow({ assembly, myVotes, present, unitCount }: Props) {
    const isOpen = assembly.status === 'open';

    const attend = () => router.post(route('portal.assemblies.attend', assembly.id), {}, { preserveScroll: true });
    const vote = (itemId: string, optionId: string) =>
        router.post(route('portal.assemblies.vote', [assembly.id, itemId]), { option_id: optionId }, { preserveScroll: true });

    return (
        <PortalLayout>
            <Head title={assembly.title} />

            <Link href={route('portal.assemblies.index')} className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <ArrowLeft className="h-4 w-4" /> Assembleias
            </Link>

            <div className="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <div className="border-b border-gray-100 p-5">
                    <h1 className="flex items-center gap-2 text-lg font-bold text-gray-900"><Vote className="h-5 w-5" /> {assembly.title}</h1>
                    <p className="text-xs text-gray-500">
                        {assembly.condominium?.name}{assembly.scheduled_at ? ` · ${new Date(assembly.scheduled_at).toLocaleString('pt-BR')}` : ''}
                    </p>
                    {assembly.description && <p className="mt-2 text-sm text-gray-700">{assembly.description}</p>}
                    {isOpen && (
                        <div className="mt-3">
                            {present ? (
                                <span className="inline-flex items-center gap-1 text-sm font-medium text-green-700"><CheckCircle2 className="h-4 w-4" /> Presença registrada</span>
                            ) : (
                                <button onClick={attend} className="rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700">Registrar presença</button>
                            )}
                            {unitCount > 1 && <p className="mt-1 text-xs text-gray-400">Seu voto vale por {unitCount} unidade(s).</p>}
                        </div>
                    )}
                </div>

                <div className="space-y-4 p-5">
                    {assembly.items.map((it, i) => (
                        <div key={it.id} className="rounded-lg border border-gray-100 p-4">
                            <p className="text-sm font-medium text-gray-900">{i + 1}. {it.title}</p>
                            {it.description && <p className="mb-2 text-xs text-gray-500">{it.description}</p>}
                            <div className="mt-2 space-y-2">
                                {it.options.map((o) => {
                                    const chosen = myVotes[it.id] === o.id;
                                    return (
                                        <button
                                            key={o.id}
                                            disabled={!isOpen}
                                            onClick={() => vote(it.id, o.id)}
                                            className={`flex w-full items-center justify-between rounded-lg border px-3 py-2 text-left text-sm ${chosen ? 'border-blue-500 bg-blue-50 font-medium text-blue-700' : 'border-gray-200 text-gray-700 hover:bg-gray-50'} disabled:opacity-60`}
                                        >
                                            {o.label}
                                            {chosen && <CheckCircle2 className="h-4 w-4 text-blue-600" />}
                                        </button>
                                    );
                                })}
                            </div>
                            {!isOpen && myVotes[it.id] && <p className="mt-2 text-xs text-gray-400">Seu voto foi registrado.</p>}
                        </div>
                    ))}

                    {!isOpen && assembly.minutes && (
                        <div className="rounded-lg border border-gray-100 bg-gray-50 p-4">
                            <p className="mb-2 text-sm font-semibold text-gray-900">Ata</p>
                            <p className="whitespace-pre-wrap text-sm text-gray-700">{assembly.minutes}</p>
                        </div>
                    )}
                </div>
            </div>
        </PortalLayout>
    );
}
