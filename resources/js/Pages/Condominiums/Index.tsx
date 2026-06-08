import AppLayout from '@/Layouts/AppLayout';
import CondominiumLogo from '@/Components/CondominiumLogo';
import { Head, Link, router } from '@inertiajs/react';
import { Building2, Plus, Search, MapPin, Users, Grid3X3 } from 'lucide-react';
import { useState } from 'react';

interface Manager { person: { name: string }; role: string }
interface Condominium {
    id: string; name: string; cnpj: string | null; city: string | null; state: string | null;
    status: string; logo_url: string | null; blocks_count: number; units_count: number; active_managers: Manager[];
}
interface Usage {
    current: number;
    limit: number;
    unlimited: boolean;
}
interface Props {
    condominiums: { data: Condominium[]; meta: any };
    filters: { search?: string; status?: string };
    usage: Usage;
}

export default function CondominiumsIndex({ condominiums, filters, usage }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const canCreate = usage.unlimited || usage.current < usage.limit;

    const applySearch = () => router.get(route('condominiums.index'), { search }, { preserveState: true });

    return (
        <AppLayout>
            <Head title="Condomínios" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Condomínios</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            {usage.current} condomínio(s) cadastrado(s)
                            {!usage.unlimited && ` de ${usage.limit} permitido(s) no plano`}
                        </p>
                    </div>
                    {canCreate ? (
                        <Link href={route('condominiums.create')} className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                            <Plus className="h-4 w-4" /> Novo Condomínio
                        </Link>
                    ) : (
                        <button disabled className="inline-flex cursor-not-allowed items-center gap-2 rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-400">
                            <Plus className="h-4 w-4" /> Limite atingido
                        </button>
                    )}
                </div>

                {/* Busca */}
                <div className="flex gap-3">
                    <div className="relative flex-1 max-w-sm">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <input
                            type="text"
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && applySearch()}
                            placeholder="Buscar por nome ou cidade…"
                            className="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                    </div>
                    <button onClick={applySearch} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        Buscar
                    </button>
                </div>

                {condominiums.data.length === 0 ? (
                    <div className="rounded-xl border-2 border-dashed border-gray-200 bg-white p-12 text-center">
                        <Building2 className="mx-auto h-12 w-12 text-gray-400" />
                        <h3 className="mt-4 text-lg font-semibold text-gray-900">Nenhum condomínio cadastrado</h3>
                        <p className="mt-2 text-sm text-gray-500">Comece cadastrando o primeiro condomínio.</p>
                        {canCreate && (
                            <Link href={route('condominiums.create')} className="mt-6 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                                <Plus className="h-4 w-4" /> Cadastrar Condomínio
                            </Link>
                        )}
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {condominiums.data.map(condo => (
                            <Link key={condo.id} href={route('condominiums.show', condo.id)} className="block rounded-xl bg-white border border-gray-100 shadow-sm hover:shadow-md hover:border-blue-200 transition-all">
                                <div className="p-5">
                                    <div className="flex items-start justify-between gap-2">
                                        <CondominiumLogo src={condo.logo_url} alt={condo.name} />
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${condo.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>
                                            {condo.status === 'active' ? 'Ativo' : 'Inativo'}
                                        </span>
                                    </div>
                                    <h3 className="mt-3 text-sm font-semibold text-gray-900 line-clamp-2">{condo.name}</h3>
                                    {(condo.city || condo.state) && (
                                        <p className="mt-1 flex items-center gap-1 text-xs text-gray-500">
                                            <MapPin className="h-3 w-3" />
                                            {[condo.city, condo.state].filter(Boolean).join(' – ')}
                                        </p>
                                    )}
                                    <div className="mt-4 flex items-center gap-4 text-xs text-gray-500">
                                        <span className="flex items-center gap-1"><Grid3X3 className="h-3.5 w-3.5" />{condo.blocks_count} blocos</span>
                                        <span className="flex items-center gap-1"><Building2 className="h-3.5 w-3.5" />{condo.units_count} unidades</span>
                                    </div>
                                    {condo.active_managers.length > 0 && (
                                        <div className="mt-3 border-t border-gray-100 pt-3">
                                            <p className="text-xs text-gray-500">
                                                <Users className="inline h-3 w-3 mr-1" />
                                                {condo.active_managers.find(m => m.role === 'sindico')?.person.name ?? 'Sem síndico'}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
