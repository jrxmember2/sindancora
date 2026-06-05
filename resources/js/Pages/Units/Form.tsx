import { Link } from '@inertiajs/react';
import { Plus, Trash2, X, UserRound, Users, Home, PawPrint } from 'lucide-react';
import { maskPhone, maskCpf, maskDate } from '@/lib/masks';

interface Block { id: string; name: string }

export interface PersonItem { id?: string; name: string; cpf: string; birth_date: string; phones: string[]; emails: string[] }
export interface PetItem { id?: string; name: string; species: string; breed: string; notes: string }

export interface UnitFormData {
    number: string; block_id: string; floor: string; type: string;
    area_m2: string; fraction: string; status: string;
    owners: PersonItem[]; tenants: PersonItem[]; family: PersonItem[]; pets: PetItem[];
}

interface Props {
    data: UnitFormData;
    setData: <K extends keyof UnitFormData>(key: K, value: UnitFormData[K]) => void;
    errors: Record<string, string>;
    processing: boolean;
    onSubmit: () => void;
    condominium: { id: string; name: string };
    blocks: Block[];
    typeLabels: Record<string, string>;
    statusLabels: Record<string, string>;
    petSpecies: Record<string, string>;
    submitLabel: string;
}

export const emptyPerson = (): PersonItem => ({ name: '', cpf: '', birth_date: '', phones: [''], emails: [''] });
export const emptyPet = (): PetItem => ({ name: '', species: 'dog', breed: '', notes: '' });

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
            {children}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

function Input(props: React.InputHTMLAttributes<HTMLInputElement>) {
    return <input className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" {...props} />;
}

function Select({ value, onChange, children }: { value: string; onChange: (v: string) => void; children: React.ReactNode }) {
    return (
        <select value={value} onChange={(e) => onChange(e.target.value)} className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
            {children}
        </select>
    );
}

/** Lista de strings com máscara opcional e botões +/- (telefones, emails). */
function StringList({ label, values, onChange, mask, placeholder, inputType }: { label: string; values: string[]; onChange: (v: string[]) => void; mask?: (v: string) => string; placeholder?: string; inputType?: string }) {
    const list = values.length ? values : [''];
    const update = (i: number, v: string) => onChange(list.map((x, idx) => (idx === i ? (mask ? mask(v) : v) : x)));
    const add = () => onChange([...list, '']);
    const remove = (i: number) => onChange(list.filter((_, idx) => idx !== i).length ? list.filter((_, idx) => idx !== i) : ['']);

    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">{label}</label>
            <div className="space-y-1.5">
                {list.map((v, i) => (
                    <div key={i} className="flex items-center gap-1.5">
                        <Input type={inputType ?? 'text'} value={v} placeholder={placeholder} onChange={(e) => update(i, e.target.value)} />
                        <button type="button" onClick={() => remove(i)} className="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Remover"><X className="h-4 w-4" /></button>
                    </div>
                ))}
            </div>
            <button type="button" onClick={add} className="mt-1 inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-700">
                <Plus className="h-3.5 w-3.5" /> Adicionar
            </button>
        </div>
    );
}

function PersonFieldset({ person, onChange, onRemove }: { person: PersonItem; onChange: (p: PersonItem) => void; onRemove: () => void }) {
    const set = (patch: Partial<PersonItem>) => onChange({ ...person, ...patch });

    return (
        <div className="rounded-lg border border-gray-200 p-4">
            <div className="mb-3 flex items-start gap-3">
                <div className="flex-1">
                    <Field label="Nome">
                        <Input value={person.name} onChange={(e) => set({ name: e.target.value })} placeholder="Nome completo" />
                    </Field>
                </div>
                <button type="button" onClick={onRemove} className="mt-6 rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Remover pessoa"><Trash2 className="h-4 w-4" /></button>
            </div>
            <div className="grid grid-cols-2 gap-3">
                <Field label="CPF">
                    <Input value={person.cpf} onChange={(e) => set({ cpf: maskCpf(e.target.value) })} placeholder="000.000.000-00" inputMode="numeric" />
                </Field>
                <Field label="Nascimento">
                    <Input value={person.birth_date} onChange={(e) => set({ birth_date: maskDate(e.target.value) })} placeholder="dd/mm/aaaa" inputMode="numeric" />
                </Field>
            </div>
            <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <StringList label="Telefones" values={person.phones} onChange={(phones) => set({ phones })} mask={maskPhone} placeholder="(00) 00000-0000" inputType="tel" />
                <StringList label="E-mails" values={person.emails} onChange={(emails) => set({ emails })} placeholder="email@exemplo.com" inputType="email" />
            </div>
        </div>
    );
}

function PeopleSection({ icon: Icon, title, items, onChange, addLabel }: { icon: typeof UserRound; title: string; items: PersonItem[]; onChange: (items: PersonItem[]) => void; addLabel: string }) {
    return (
        <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 className="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-900"><Icon className="h-5 w-5 text-blue-600" /> {title}</h2>
            <div className="space-y-3">
                {items.map((p, i) => (
                    <PersonFieldset key={i} person={p} onChange={(np) => onChange(items.map((x, idx) => (idx === i ? np : x)))} onRemove={() => onChange(items.filter((_, idx) => idx !== i))} />
                ))}
            </div>
            <button type="button" onClick={() => onChange([...items, emptyPerson()])} className="mt-3 inline-flex items-center gap-1.5 rounded-lg border border-dashed border-gray-300 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                <Plus className="h-4 w-4" /> {addLabel}
            </button>
        </div>
    );
}

function PetsSection({ items, onChange, petSpecies }: { items: PetItem[]; onChange: (items: PetItem[]) => void; petSpecies: Record<string, string> }) {
    const set = (i: number, patch: Partial<PetItem>) => onChange(items.map((x, idx) => (idx === i ? { ...x, ...patch } : x)));

    return (
        <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h2 className="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-900"><PawPrint className="h-5 w-5 text-blue-600" /> Pets</h2>
            <div className="space-y-3">
                {items.map((p, i) => (
                    <div key={i} className="rounded-lg border border-gray-200 p-4">
                        <div className="flex items-start gap-3">
                            <div className="grid flex-1 grid-cols-2 gap-3">
                                <Field label="Nome"><Input value={p.name} onChange={(e) => set(i, { name: e.target.value })} placeholder="Nome do pet" /></Field>
                                <Field label="Espécie">
                                    <Select value={p.species} onChange={(v) => set(i, { species: v })}>
                                        {Object.entries(petSpecies).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                                    </Select>
                                </Field>
                                <Field label="Raça"><Input value={p.breed} onChange={(e) => set(i, { breed: e.target.value })} placeholder="Opcional" /></Field>
                                <Field label="Observações"><Input value={p.notes} onChange={(e) => set(i, { notes: e.target.value })} placeholder="Opcional" /></Field>
                            </div>
                            <button type="button" onClick={() => onChange(items.filter((_, idx) => idx !== i))} className="mt-6 rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Remover pet"><Trash2 className="h-4 w-4" /></button>
                        </div>
                    </div>
                ))}
            </div>
            <button type="button" onClick={() => onChange([...items, emptyPet()])} className="mt-3 inline-flex items-center gap-1.5 rounded-lg border border-dashed border-gray-300 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                <Plus className="h-4 w-4" /> Adicionar pet
            </button>
        </div>
    );
}

export default function UnitForm({ data, setData, errors, processing, onSubmit, condominium, blocks, typeLabels, statusLabels, petSpecies, submitLabel }: Props) {
    return (
        <div className="mx-auto max-w-3xl space-y-6">
            {/* Dados da unidade */}
            <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 className="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-900"><Home className="h-5 w-5 text-blue-600" /> Dados da unidade</h2>
                <div className="space-y-5">
                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Número *" error={errors.number}>
                            <Input value={data.number} onChange={(e) => setData('number', e.target.value)} placeholder="101, A-01…" />
                        </Field>
                        <Field label="Bloco" error={errors.block_id}>
                            <Select value={data.block_id} onChange={(v) => setData('block_id', v)}>
                                <option value="">Sem bloco</option>
                                {blocks.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
                            </Select>
                        </Field>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Tipo *" error={errors.type}>
                            <Select value={data.type} onChange={(v) => setData('type', v)}>
                                {Object.entries(typeLabels).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                            </Select>
                        </Field>
                        <Field label="Status *" error={errors.status}>
                            <Select value={data.status} onChange={(v) => setData('status', v)}>
                                {Object.entries(statusLabels).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                            </Select>
                        </Field>
                    </div>
                    <div className="grid grid-cols-3 gap-4">
                        <Field label="Andar" error={errors.floor}><Input type="number" value={data.floor} onChange={(e) => setData('floor', e.target.value)} placeholder="1" /></Field>
                        <Field label="Área (m²)" error={errors.area_m2}><Input type="number" step="0.01" value={data.area_m2} onChange={(e) => setData('area_m2', e.target.value)} placeholder="0,00" /></Field>
                        <Field label="Fração Ideal" error={errors.fraction}><Input type="number" step="0.000001" value={data.fraction} onChange={(e) => setData('fraction', e.target.value)} placeholder="0,000000" /></Field>
                    </div>
                </div>
            </div>

            <PeopleSection icon={UserRound} title="Proprietários" items={data.owners} onChange={(v) => setData('owners', v)} addLabel="Adicionar proprietário" />
            <PeopleSection icon={UserRound} title="Inquilinos" items={data.tenants} onChange={(v) => setData('tenants', v)} addLabel="Adicionar inquilino" />
            <PeopleSection icon={Users} title="Familiares" items={data.family} onChange={(v) => setData('family', v)} addLabel="Adicionar familiar" />
            <PetsSection items={data.pets} onChange={(v) => setData('pets', v)} petSpecies={petSpecies} />

            <div className="flex justify-between">
                <Link href={route('condominiums.units.index', condominium.id)} className="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</Link>
                <button onClick={onSubmit} disabled={processing} className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    {processing ? 'Salvando…' : submitLabel}
                </button>
            </div>
        </div>
    );
}
