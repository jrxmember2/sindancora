import PortalLayout from '@/Layouts/PortalLayout';
import { Head, Link } from '@inertiajs/react';
import { Building2, Mail, Phone, UserRound } from 'lucide-react';

interface Link2 {
    id: string; type: string; is_primary: boolean; start_date: string | null; end_date: string | null;
    unit: { number: string | null; type: string | null; area: string | number | null; block: string | null; condominium: string | null };
}
interface Props {
    person: { name: string; email: string | null; phone: string | null };
    links: Link2[];
    linkTypes: Record<string, string>;
}

export default function PortalUnit({ person, links, linkTypes }: Props) {
    const active = links.filter((l) => !l.end_date);
    const history = links.filter((l) => l.end_date);

    return (
        <PortalLayout title="Minha unidade">
            <Head title="Minha unidade" />

            <div className="space-y-6">
                {/* Meus dados */}
                <div className="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 className="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900"><UserRound className="h-4 w-4 text-gray-400" /> Meus dados</h2>
                    <p className="text-sm font-medium text-gray-900">{person.name}</p>
                    {person.email && <p className="mt-1 flex items-center gap-2 text-sm text-gray-600"><Mail className="h-3.5 w-3.5 text-gray-400" /> {person.email}</p>}
                    {person.phone && <p className="mt-1 flex items-center gap-2 text-sm text-gray-600"><Phone className="h-3.5 w-3.5 text-gray-400" /> {person.phone}</p>}
                    <Link href={route('portal.profile.edit')} className="mt-3 inline-block text-sm text-blue-600 hover:text-blue-700">Editar perfil →</Link>
                </div>

                {/* Vínculos ativos */}
                <div>
                    <h2 className="mb-2 text-base font-semibold text-gray-900">Unidade(s)</h2>
                    <div className="space-y-2">
                        {active.length === 0 && <p className="rounded-xl border border-gray-100 bg-white px-4 py-6 text-center text-sm text-gray-400 shadow-sm">Nenhum vínculo ativo.</p>}
                        {active.map((l) => (
                            <div key={l.id} className="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                                <div className="flex items-center gap-3">
                                    <Building2 className="h-5 w-5 text-blue-500" />
                                    <div>
                                        <p className="text-sm font-medium text-gray-900">
                                            {l.unit.condominium} · {l.unit.block ? l.unit.block + ' · ' : ''}{l.unit.number}
                                            {l.is_primary && <span className="ml-2 rounded-full bg-blue-100 px-2 py-0.5 text-[11px] text-blue-700">Principal</span>}
                                        </p>
                                        <p className="text-xs text-gray-500">
                                            {linkTypes[l.type] ?? l.type}
                                            {l.unit.type ? ` · ${l.unit.type}` : ''}
                                            {l.unit.area ? ` · ${l.unit.area} m²` : ''}
                                            {l.start_date ? ` · desde ${new Date(l.start_date).toLocaleDateString('pt-BR')}` : ''}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Histórico */}
                {history.length > 0 && (
                    <div>
                        <h2 className="mb-2 text-base font-semibold text-gray-900">Histórico</h2>
                        <div className="space-y-2">
                            {history.map((l) => (
                                <div key={l.id} className="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 opacity-70">
                                    <Building2 className="h-4 w-4 text-gray-400" />
                                    <div>
                                        <p className="text-sm text-gray-700">{l.unit.condominium} · {l.unit.number}</p>
                                        <p className="text-xs text-gray-500">
                                            {linkTypes[l.type] ?? l.type} · {l.start_date ? new Date(l.start_date).toLocaleDateString('pt-BR') : '?'} → {l.end_date ? new Date(l.end_date).toLocaleDateString('pt-BR') : '?'}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </PortalLayout>
    );
}
